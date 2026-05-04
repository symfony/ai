<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt\TokenUsageExtractor;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TransportInterface;

/**
 * OpenAI /v1/responses (Responses API) contract handler.
 *
 * Owns the request reshape for structured output (response_format → text.format),
 * the parsing of `output[]` arrays into Symfony AI result objects, and SSE-style
 * streaming over the same payload format. HTTP status mapping (401/400/429)
 * is delegated to {@see HttpTransport}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type OutputMessage array{content: array<array{type: string, text?: string, refusal?: string}>, id: string, role: string, type: 'message'}
 * @phpstan-type FunctionCall array{id: string, arguments: string, call_id: string, name: string, type: 'function_call'}
 * @phpstan-type Reasoning array{summary: array{text?: string}, id: string, type: 'reasoning'}
 * @phpstan-type ErrorBody array{code?: string|null, type?: string|null, param?: string|null, message?: string|null}
 */
final class ResponsesClient implements EndpointClientInterface
{
    public const ENDPOINT = 'openai.responses';

    private const KEY_OUTPUT = 'output';

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        // OpenAI performs automatic prompt caching; cacheRetention is not an
        // OpenAI concept and would be rejected by the Responses API.
        unset($options['cacheRetention']);

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $envelope = new RequestEnvelope(
            payload: array_merge($options, ['model' => $model->getName()], $payload),
            path: '/v1/responses',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException($this->formatError($data['error']));
        }

        if (!isset($data[self::KEY_OUTPUT])) {
            throw new RuntimeException('Response does not contain output.');
        }

        $results = $this->convertOutputArray($data[self::KEY_OUTPUT]);

        return 1 === \count($results) ? array_pop($results) : new ChoiceResult($results);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<OutputMessage|FunctionCall|Reasoning> $output
     *
     * @return list<ResultInterface>
     */
    private function convertOutputArray(array $output): array
    {
        [$toolCallResult, $output] = $this->extractFunctionCalls($output);

        $results = array_values(array_filter(array_map($this->processOutputItem(...), $output)));
        if ($toolCallResult) {
            $results[] = $toolCallResult;
        }

        return $results;
    }

    /**
     * @param OutputMessage|Reasoning $item
     */
    private function processOutputItem(array $item): ?ResultInterface
    {
        $type = $item['type'] ?? null;

        return match ($type) {
            'message' => $this->convertOutputMessage($item),
            'reasoning' => $this->convertReasoning($item),
            default => throw new RuntimeException(\sprintf('Unsupported output type "%s".', (string) $type)),
        };
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $currentThinking = null;

        foreach ($result->getDataStream() as $event) {
            $type = $event['type'] ?? '';

            if ('error' === $type && isset($event['error'])) {
                throw new RuntimeException($this->formatError($event['error']));
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

            if (!str_contains($type, 'completed')) {
                continue;
            }

            [$toolCallResult] = $this->extractFunctionCalls($event['response'][self::KEY_OUTPUT] ?? []);

            if ($toolCallResult && 'response.completed' === $type) {
                yield new ToolCallComplete($toolCallResult->getContent());
            }
        }
    }

    /**
     * @param array<OutputMessage|FunctionCall|Reasoning> $output
     *
     * @return array{0: ToolCallResult|null, 1: array<OutputMessage|Reasoning>}
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

        return [$toolCallResult, array_values($output)];
    }

    /**
     * @param OutputMessage $output
     */
    private function convertOutputMessage(array $output): ?TextResult
    {
        $content = $output['content'] ?? [];
        if ([] === $content) {
            return null;
        }

        $last = array_pop($content);
        if ('refusal' === ($last['type'] ?? null)) {
            return new TextResult(\sprintf('Model refused to generate output: %s', $last['refusal'] ?? ''));
        }

        return new TextResult($last['text'] ?? '');
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
     * @param Reasoning $item
     */
    private function convertReasoning(array $item): ?ResultInterface
    {
        // Reasoning is sometimes missing if it exceeds the context limit.
        $summary = $item['summary']['text'] ?? null;

        return $summary ? new TextResult($summary) : null;
    }

    /**
     * @param ErrorBody $error
     */
    private function formatError(array $error): string
    {
        return \sprintf('Error "%s"-%s (%s): "%s".', $error['code'] ?? '-', $error['type'] ?? '-', $error['param'] ?? '-', $error['message'] ?? '-');
    }
}
