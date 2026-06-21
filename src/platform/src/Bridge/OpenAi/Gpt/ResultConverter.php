<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Gpt;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 *
 * @phpstan-type OutputMessage array{content: array<Refusal|OutputText>, id: string, role: string, type: 'message'}
 * @phpstan-type OutputText array{type: 'output_text', text: string}
 * @phpstan-type Refusal array{type: 'refusal', refusal: string}
 * @phpstan-type FunctionCall array{id: string, arguments: string, call_id: string, name: string, type: 'function_call'}
 * @phpstan-type Thinking array{summary: list<array{type: string, text?: string}>, id: string}
 * @phpstan-type Error array{code?: string|null, type?: string|null, param?: string|null, message?: string|null}
 */
final class ResultConverter implements ResultConverterInterface
{
    private const KEY_OUTPUT = 'output';

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (401 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'];
            throw new AuthenticationException($errorMessage);
        }

        if (400 === $response->getStatusCode()) {
            $error = json_decode($response->getContent(false), true)['error'] ?? [];
            $errorMessage = $error['message'] ?? 'Bad Request';

            if ('context_length_exceeded' === ($error['code'] ?? null)) {
                throw new ExceedContextSizeException($errorMessage);
            }

            throw new BadRequestException($errorMessage);
        }

        if (429 === $response->getStatusCode()) {
            $headers = $response->getHeaders(false);
            $resetTime = $headers['x-ratelimit-reset-requests'][0]
                ?? $headers['x-ratelimit-reset-tokens'][0]
                ?? null;
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;

            throw new RateLimitExceededException($resetTime ? self::parseResetTime($resetTime) : null, $errorMessage);
        }

        if ($options['stream'] ?? false) {
            if (($code = $response->getStatusCode()) >= 400) {
                throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $response->getContent(false)));
            }

            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException($this->generateErrorMessage($data['error']));
        }

        if (!isset($data[self::KEY_OUTPUT])) {
            throw new RuntimeException('Response does not contain output.');
        }

        $results = $this->convertOutputArray($data[self::KEY_OUTPUT]);

        return 1 === \count($results) ? array_pop($results) : new MultiPartResult(array_values($results));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<OutputMessage|FunctionCall|Thinking> $output
     *
     * @return ResultInterface[]
     */
    private function convertOutputArray(array $output): array
    {
        [$toolCallResult, $output] = $this->extractFunctionCalls($output);

        $results = [];
        foreach ($output as $item) {
            foreach ($this->processOutputItem($item) as $result) {
                $results[] = $result;
            }
        }
        if ($toolCallResult) {
            $results[] = $toolCallResult;
        }

        return $results;
    }

    /**
     * @param OutputMessage|Thinking $item
     *
     * @return iterable<ResultInterface>
     */
    private function processOutputItem(array $item): iterable
    {
        $type = $item['type'] ?? null;

        return match ($type) {
            'message' => $this->convertOutputMessage($item),
            'reasoning' => $this->convertReasoning($item),
            // Built-in server-side tool calls (web_search, file_search, code_interpreter,
            // image generation, computer use, local shell, MCP, …) appear as their own
            // output items but carry no assistant-facing result. Skip them so the actual
            // message item is still converted instead of aborting the whole response.
            'web_search_call', 'file_search_call', 'code_interpreter_call',
            'image_generation_call', 'computer_call', 'local_shell_call',
            'mcp_call', 'mcp_list_tools', 'mcp_approval_request' => [],
            default => throw new RuntimeException(\sprintf('Unsupported output type "%s".', $type)),
        };
    }

    private function convertStream(RawResultInterface|RawHttpResult $result): \Generator
    {
        $currentThinking = null;
        /** @var array<string, ToolCall> $toolCalls */
        $toolCalls = [];
        $sawResponseEvent = false;
        $sawResponseCompleted = false;

        foreach ($result->getDataStream() as $event) {
            $type = $event['type'] ?? '';
            $sawResponseEvent = true;

            if ('error' === $type) {
                throw new RuntimeException($this->generateErrorMessage($this->extractStreamError($event)));
            }

            if ('response.failed' === $type) {
                $response = \is_array($event['response'] ?? null) ? $event['response'] : [];
                throw new RuntimeException($this->generateErrorMessage($this->extractStreamError($response)));
            }

            if ('response.incomplete' === $type) {
                $reason = $event['response']['incomplete_details']['reason'] ?? 'unknown';
                if (!\is_string($reason) || '' === $reason) {
                    $reason = 'unknown';
                }

                throw new RuntimeException(\sprintf('OpenAI Responses stream ended incomplete (%s).', $reason));
            }

            if (isset($event['response']['usage'])) {
                yield $this->getTokenUsageExtractor()->fromDataArray($event['response']);
            }

            if (str_contains($type, 'output_text') && isset($event['delta'])) {
                yield new TextDelta($event['delta']);
            }

            if ('response.reasoning_summary_text.delta' === $type && isset($event['delta'])) {
                if (null === $currentThinking) {
                    $currentThinking = '';
                    yield new ThinkingStart();
                }
                $currentThinking .= $event['delta'];
                yield new ThinkingDelta($event['delta']);
            }

            if ('response.reasoning_summary_text.done' === $type) {
                yield new ThinkingComplete($currentThinking ?? '');
                $currentThinking = null;
            }

            if ('response.output_item.done' === $type && \is_array($event['item'] ?? null) && 'function_call' === ($event['item']['type'] ?? null)) {
                /** @var FunctionCall $item */
                $item = $event['item'];
                $toolCalls[$item['id']] = $this->convertFunctionCall($item);
            }

            if ('response.completed' !== $type) {
                continue;
            }

            $sawResponseCompleted = true;
            [$toolCallResult] = $this->extractFunctionCalls($event['response'][self::KEY_OUTPUT] ?? []);

            if ($toolCallResult) {
                yield new ToolCallComplete($toolCallResult->getContent());
            } elseif ([] !== $toolCalls) {
                yield new ToolCallComplete(array_values($toolCalls));
            }
        }

        if ($sawResponseEvent && !$sawResponseCompleted) {
            throw new IncompleteStreamException('OpenAI Responses stream ended before response.completed.');
        }
    }

    /**
     * @param array<OutputMessage|FunctionCall|Thinking> $output
     *
     * @return list<ToolCallResult|array<OutputMessage|Thinking>|null>
     */
    private function extractFunctionCalls(array $output): array
    {
        $functionCalls = [];
        foreach ($output as $key => $item) {
            if ('function_call' === ($item['type'] ?? null)) {
                $functionCalls[] = $item;
                unset($output[$key]);
            }
        }

        $toolCallResult = $functionCalls ? new ToolCallResult(
            array_map($this->convertFunctionCall(...), $functionCalls)
        ) : null;

        return [$toolCallResult, $output];
    }

    /**
     * @param OutputMessage $output
     *
     * @return \Generator<TextResult>
     */
    private function convertOutputMessage(array $output): \Generator
    {
        $content = $output['content'] ?? [];
        if ([] === $content) {
            return;
        }

        $content = array_pop($content);
        if ('refusal' === $content['type']) {
            yield new TextResult(\sprintf('Model refused to generate output: %s', $content['refusal']));

            return;
        }

        yield new TextResult($content['text']);
    }

    /**
     * @param FunctionCall $toolCall
     *
     * @throws \JsonException
     */
    private function convertFunctionCall(array $toolCall): ToolCall
    {
        $arguments = json_decode($toolCall['arguments'], true, flags: \JSON_THROW_ON_ERROR);

        return new ToolCall($toolCall['id'], $toolCall['name'], $arguments);
    }

    /**
     * Converts OpenAI's reset time format (e.g. "1s", "6m0s", "2m30s") into seconds.
     *
     * Supported formats:
     * - "1s"
     * - "6m0s"
     * - "2m30s"
     */
    private static function parseResetTime(string $resetTime): ?int
    {
        if (preg_match('/^(?:(\d+)m)?(?:(\d+)s)?$/', $resetTime, $matches)) {
            $minutes = isset($matches[1]) ? (int) $matches[1] : 0;
            $secs = isset($matches[2]) ? (int) $matches[2] : 0;

            return ($minutes * 60) + $secs;
        }

        return null;
    }

    /**
     * @param Thinking $item
     *
     * @return \Generator<ThinkingResult>
     */
    private function convertReasoning(array $item): \Generator
    {
        // Reasoning is sometimes missing if it exceeds the context limit.
        foreach ($item['summary'] ?? [] as $entry) {
            if ('' !== ($entry['text'] ?? '')) {
                yield new ThinkingResult($entry['text']);
            }
        }
    }

    /**
     * @param Error $error
     */
    private function generateErrorMessage(array $error): string
    {
        return \sprintf('Error "%s"-%s (%s): "%s".', $error['code'] ?? '-', $error['type'] ?? '-', $error['param'] ?? '-', $error['message'] ?? '-');
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return Error
     */
    private function extractStreamError(array $event): array
    {
        if (\is_array($event['error'] ?? null)) {
            $event = $event['error'];
        }

        return [
            'code' => \is_string($event['code'] ?? null) ? $event['code'] : null,
            'type' => \is_string($event['type'] ?? null) && 'error' !== $event['type'] ? $event['type'] : null,
            'param' => \is_string($event['param'] ?? null) ? $event['param'] : null,
            'message' => \is_string($event['message'] ?? null) ? $event['message'] : null,
        ];
    }
}
