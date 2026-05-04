<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TransportInterface;

/**
 * Anthropic /v1/messages client.
 *
 * Owns the Anthropic Messages API request shape (cache-control injection,
 * tool/thinking/structured-output normalization, beta-feature header
 * collection) and response shape (tool_use / text / thinking /
 * server_tool_use parsing, streaming deltas).
 *
 * Reused by both direct Anthropic and AWS Bedrock — only the injected
 * {@see TransportInterface} differs between the two.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessagesClient implements EndpointClientInterface
{
    public const ENDPOINT = 'anthropic.messages';

    /**
     * @param 'none'|'short'|'long' $cacheRetention 'short' = 5-minute ephemeral cache,
     *                                              'long' = 1-hour cache (direct Anthropic only),
     *                                              'none' = caching disabled
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $cacheRetention = 'short',
    ) {
        if (!\in_array($cacheRetention, ['none', 'short', 'long'], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid cache retention "%s". Supported values are "none", "short" and "long".', $cacheRetention));
        }
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $headers = [];

        $payload = $this->injectCacheControl($payload);

        if (isset($options['tools'])) {
            $options['tool_choice'] = ['type' => 'auto'];
            $options['tools'] = $this->injectToolsCacheControl($options['tools']);
        }

        if (isset($options['thinking'])) {
            $options['beta_features'][] = 'interleaved-thinking-2025-05-14';
        }

        if (isset($options['response_format'])) {
            $options['output_config'] = [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $options['response_format']['json_schema']['schema'] ?? [],
                ],
            ];
            unset($options['response_format']);
        }

        if (isset($options['beta_features']) && \is_array($options['beta_features']) && [] !== $options['beta_features']) {
            $headers['anthropic-beta'] = implode(',', $options['beta_features']);
            unset($options['beta_features']);
        }

        $envelope = new RequestEnvelope(
            payload: array_merge($options, $payload),
            headers: $headers,
            path: '/v1/messages',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $response = $result->getObject();

            if (401 === $response->getStatusCode()) {
                $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Unauthorized';
                throw new AuthenticationException($errorMessage);
            }

            if (400 === $response->getStatusCode()) {
                $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Bad Request';
                throw new BadRequestException($errorMessage);
            }

            if (429 === $response->getStatusCode()) {
                $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
                $retryAfterValue = $retryAfter ? (int) $retryAfter : null;
                $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
                throw new RateLimitExceededException($retryAfterValue, $errorMessage);
            }
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['type']) && 'error' === $data['type']) {
            $type = $data['error']['type'] ?? 'Unknown';
            $message = $data['error']['message'] ?? 'An unknown error occurred.';
            throw new RuntimeException(\sprintf('API Error [%s]: "%s"', $type, $message));
        }

        if (!isset($data['content']) || [] === $data['content']) {
            throw new RuntimeException('Response does not contain any content.');
        }

        $results = [];
        foreach ($data['content'] as $content) {
            if ('tool_use' === $content['type']) {
                $results[] = new ToolCallResult([new ToolCall($content['id'], $content['name'], $content['input'])]);
                continue;
            }

            if ('text' === $content['type']) {
                $results[] = new TextResult($content['text']);
            } elseif ('server_tool_use' === $content['type']) {
                if ('bash_code_execution' === $content['name']) {
                    $results[] = new ExecutableCodeResult($content['input']['command'], 'bash', $content['id']);
                } elseif ('text_editor_code_execution' === $content['name']) {
                    $results[] = new ExecutableCodeResult($content['input']['file_text'] ?? $content['input']['command'], null, $content['id']);
                }
            } elseif ('bash_code_execution_tool_result' === $content['type']) {
                $results[] = new CodeExecutionResult(
                    0 === ($content['content']['return_code'] ?? 0),
                    ($content['content']['stdout'] ?? '').($content['content']['stderr'] ?? '') ?: null,
                    $content['tool_use_id'],
                );
            } elseif ('text_editor_code_execution_tool_result' === $content['type']) {
                $results[] = new CodeExecutionResult(true, null, $content['tool_use_id']);
            } elseif ('thinking' === $content['type']) {
                $results[] = new ThinkingResult($content['thinking'], $content['signature'] ?? null);
            }
        }

        if ([] === $results) {
            throw new RuntimeException('Response content does not contain any supported content.');
        }

        if (1 === \count($results)) {
            return $results[0];
        }

        return new MultiPartResult($results);
    }

    /**
     * Injects a prompt-caching marker on the last tool definition.
     *
     * @param list<array<string, mixed>> $tools
     *
     * @return list<array<string, mixed>>
     */
    private function injectToolsCacheControl(array $tools): array
    {
        if ('none' === $this->cacheRetention || [] === $tools) {
            return $tools;
        }

        $cacheControl = 'long' === $this->cacheRetention
            ? ['type' => 'ephemeral', 'ttl' => '1h']
            : ['type' => 'ephemeral'];

        $tools[\count($tools) - 1]['cache_control'] = $cacheControl;

        return $tools;
    }

    /**
     * Injects a prompt-caching marker on the last block of the last user message.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function injectCacheControl(array $payload): array
    {
        if ('none' === $this->cacheRetention) {
            return $payload;
        }

        $messages = $payload['messages'] ?? [];

        if ([] === $messages) {
            return $payload;
        }

        $cacheControl = 'long' === $this->cacheRetention
            ? ['type' => 'ephemeral', 'ttl' => '1h']
            : ['type' => 'ephemeral'];

        for ($i = \count($messages) - 1; $i >= 0; --$i) {
            if ('user' !== ($messages[$i]['role'] ?? '')) {
                continue;
            }

            $content = $messages[$i]['content'] ?? null;

            if (\is_string($content)) {
                $messages[$i]['content'] = [
                    ['type' => 'text', 'text' => $content, 'cache_control' => $cacheControl],
                ];
                break;
            }

            if (\is_array($content) && [] !== $content) {
                $lastIdx = \count($content) - 1;
                if (\is_array($content[$lastIdx])) {
                    $content[$lastIdx]['cache_control'] = $cacheControl;
                    $messages[$i]['content'] = $content;
                }
                break;
            }
        }

        $payload['messages'] = $messages;

        return $payload;
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        $currentToolCall = null;
        $currentToolCallJson = '';
        $currentThinking = null;
        $currentThinkingSignature = null;

        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';

            if ('message_start' === $type && isset($data['message']['usage'])) {
                yield $this->getTokenUsageExtractor()->extractFromArray($data['message']['usage']);
            }

            if ('message_delta' === $type && isset($data['usage'])) {
                yield $this->getTokenUsageExtractor()->extractFromArray($data['usage']);
            }

            if ('content_block_delta' === $type && isset($data['delta']['text'])) {
                yield new TextDelta($data['delta']['text']);
                continue;
            }

            if ('content_block_start' === $type
                && isset($data['content_block']['type'])
                && 'thinking' === $data['content_block']['type']
            ) {
                $currentThinking = '';
                $currentThinkingSignature = null;
                yield new ThinkingStart();
                continue;
            }

            if ('content_block_delta' === $type
                && isset($data['delta']['type'])
                && 'thinking_delta' === $data['delta']['type']
            ) {
                $thinking = $data['delta']['thinking'] ?? '';
                $currentThinking .= $thinking;
                yield new ThinkingDelta($thinking);
                continue;
            }

            if ('content_block_delta' === $type
                && isset($data['delta']['type'])
                && 'signature_delta' === $data['delta']['type']
            ) {
                $signature = $data['delta']['signature'] ?? '';
                $currentThinkingSignature = ($currentThinkingSignature ?? '').$signature;
                yield new ThinkingSignature($signature);
                continue;
            }

            if ('content_block_start' === $type
                && isset($data['content_block']['type'])
                && 'tool_use' === $data['content_block']['type']
            ) {
                $currentToolCall = [
                    'id' => $data['content_block']['id'],
                    'name' => $data['content_block']['name'],
                ];
                $currentToolCallJson = '';
                yield new ToolCallStart($data['content_block']['id'], $data['content_block']['name']);
                continue;
            }

            if ('content_block_delta' === $type
                && isset($data['delta']['type'])
                && 'input_json_delta' === $data['delta']['type']
            ) {
                $partialJson = $data['delta']['partial_json'] ?? '';
                $currentToolCallJson .= $partialJson;
                if (null !== $currentToolCall) {
                    yield new ToolInputDelta($currentToolCall['id'], $currentToolCall['name'], $partialJson);
                }
                continue;
            }

            if ('content_block_stop' === $type) {
                if (null !== $currentThinking) {
                    yield new ThinkingComplete($currentThinking, $currentThinkingSignature);
                    $currentThinking = null;
                    $currentThinkingSignature = null;
                    continue;
                }

                if (null !== $currentToolCall) {
                    $input = '' !== $currentToolCallJson
                        ? json_decode($currentToolCallJson, true, flags: \JSON_THROW_ON_ERROR)
                        : [];
                    $toolCalls[] = new ToolCall(
                        $currentToolCall['id'],
                        $currentToolCall['name'],
                        $input
                    );
                    $currentToolCall = null;
                    $currentToolCallJson = '';
                    continue;
                }
            }

            if ('message_stop' === $type && [] !== $toolCalls) {
                yield new ToolCallComplete($toolCalls);
            }
        }
    }
}
