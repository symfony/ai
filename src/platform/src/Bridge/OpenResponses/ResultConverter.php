<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses;

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
 *
 * @phpstan-type OutputMessage array{content: array<Refusal|OutputText>, id: string, role: string, type: 'message'}
 * @phpstan-type OutputText array{type: 'output_text', text: string}
 * @phpstan-type Refusal array{type: 'refusal', refusal: string}
 * @phpstan-type FunctionCall array{id?: string|null, arguments: string, call_id?: string|null, name: string, type: 'function_call'}
 * @phpstan-type Thinking array{summary: list<array{type: string, text?: string}>, id: string}
 * @phpstan-type Error array{code?: string|null, type?: string|null, param?: string|null, message?: string|null}
 */
final class ResultConverter implements ResultConverterInterface
{
    private const KEY_OUTPUT = 'output';

    public function supports(Model $model): bool
    {
        return $model instanceof ResponsesModel;
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

            if ('context_length_exceeded' === ($error['code'] ?? null)
                || str_contains($errorMessage, 'exceeds the context window')
                || str_contains($errorMessage, 'max_model_len')
            ) {
                throw new ExceedContextSizeException($errorMessage);
            }

            throw new BadRequestException($errorMessage);
        }

        if (429 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
            throw new RateLimitExceededException(null, $errorMessage);
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException($this->generateErrorMessage($this->extractStreamError($data)));
        }

        if (!isset($data[self::KEY_OUTPUT])) {
            throw new RuntimeException('Response does not contain output.');
        }

        $results = $this->convertOutputArray($data[self::KEY_OUTPUT]);

        if ([] === $results) {
            if ('incomplete' === ($data['status'] ?? null)) {
                $reason = $data['incomplete_details']['reason'] ?? 'unknown';
                if (!\is_string($reason) || '' === $reason) {
                    $reason = 'unknown';
                }

                throw new RuntimeException(\sprintf('Responses API response is incomplete (%s) and contains no content.', $reason));
            }

            throw new RuntimeException('Response does not contain any content.');
        }

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

                throw new RuntimeException(\sprintf('Responses API stream ended incomplete (%s).', $reason));
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
                $toolCall = $this->convertFunctionCall($item);
                $toolCalls[$toolCall->getId()] = $toolCall;
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
            throw new IncompleteStreamException('Responses API stream ended before response.completed.');
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

        // The Responses API addresses tool results by "call_id"; some providers (e.g. Scaleway)
        // only send "call_id" and leave "id" empty, so prefer it and fall back to "id".
        $id = $toolCall['call_id'] ?? $toolCall['id'] ?? null;
        if (null === $id) {
            throw new RuntimeException('Function call is missing both "call_id" and "id".');
        }

        return new ToolCall($id, $toolCall['name'], $arguments);
    }

    /**
     * @param Thinking $item
     *
     * @return \Generator<ThinkingResult>
     */
    private function convertReasoning(array $item): \Generator
    {
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
