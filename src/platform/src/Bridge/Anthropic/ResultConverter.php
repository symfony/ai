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

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\MaxOutputTokensException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
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
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function convert(RawHttpResult|RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (401 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Unauthorized';
            throw new AuthenticationException($errorMessage);
        }

        if (400 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Bad Request';

            if (str_contains($errorMessage, 'prompt is too long')) {
                throw new ExceedContextSizeException($errorMessage);
            }

            throw new BadRequestException($errorMessage);
        }

        if (429 === $response->getStatusCode()) {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
            $retryAfterValue = $retryAfter ? (int) $retryAfter : null;
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
            throw new RateLimitExceededException($retryAfterValue, $errorMessage);
        }

        if (($code = $response->getStatusCode()) >= 500) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
            throw new ServerException($code, $errorMessage);
        }

        if ($options['stream'] ?? false) {
            if (($code = $response->getStatusCode()) >= 400) {
                throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $response->getContent(false)));
            }

            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['type']) && 'error' === $data['type']) {
            $type = $data['error']['type'] ?? 'Unknown';
            $message = $data['error']['message'] ?? 'An unknown error occurred.';

            if ('rate_limit_error' === $type) {
                throw new RateLimitExceededException(null, \sprintf('API Error [%s]: "%s"', $type, $message));
            }

            if (\in_array($type, ['overloaded_error', 'api_error'], true)) {
                throw new ServerException(null, \sprintf('API Error [%s]: "%s"', $type, $message));
            }

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

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        $currentToolCall = null;
        $currentToolCallJson = '';
        $currentThinking = null;
        $currentThinkingSignature = null;
        $inMessage = false;
        $stopReason = null;
        $outputTokens = null;

        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';

            if ('error' === $type) {
                $message = $data['error']['message'] ?? 'Unknown Anthropic stream error.';

                if ('rate_limit_error' === ($data['error']['type'] ?? null)) {
                    throw new RateLimitExceededException(null, $message);
                }

                if (\in_array($data['error']['type'] ?? null, ['overloaded_error', 'api_error'], true)) {
                    throw new ServerException(null, $message);
                }

                throw new RuntimeException($message);
            }

            if ('message_start' === $type) {
                $inMessage = true;
            }

            // Anthropic reports usage in both message_start and message_delta:
            // message_start carries the prompt and cache token counts plus a
            // provisional output_tokens, and message_delta repeats the same
            // cumulative prompt/cache counts with the final output_tokens. As
            // the stream aggregation sums every yielded usage, emitting the full
            // payload from both events would double-count input and cache tokens.
            // Yield the prompt/cache counts once (message_start, without the
            // provisional output) and the final output once (message_delta).
            if ('message_start' === $type && isset($data['message']['usage'])) {
                $usage = $data['message']['usage'];
                unset($usage['output_tokens']);
                yield $this->getTokenUsageExtractor()->extractFromArray($usage);
            }

            if ('message_delta' === $type) {
                $stopReason = $data['delta']['stop_reason'] ?? $stopReason;

                if (isset($data['usage'])) {
                    $outputTokens = $data['usage']['output_tokens'] ?? $outputTokens;
                    yield $this->getTokenUsageExtractor()->extractFromArray([
                        'output_tokens' => $outputTokens ?? 0,
                    ]);
                }
            }

            // Handle text content deltas
            if ('content_block_delta' === $type && isset($data['delta']['text'])) {
                yield new TextDelta($data['delta']['text']);
                continue;
            }

            // Handle thinking content block start
            if ('content_block_start' === $type
                && isset($data['content_block']['type'])
                && 'thinking' === $data['content_block']['type']
            ) {
                $currentThinking = '';
                $currentThinkingSignature = null;
                yield new ThinkingStart();
                continue;
            }

            // Handle thinking content deltas
            if ('content_block_delta' === $type
                && isset($data['delta']['type'])
                && 'thinking_delta' === $data['delta']['type']
            ) {
                $thinking = $data['delta']['thinking'] ?? '';
                $currentThinking .= $thinking;
                yield new ThinkingDelta($thinking);
                continue;
            }

            // Handle thinking signature deltas
            if ('content_block_delta' === $type
                && isset($data['delta']['type'])
                && 'signature_delta' === $data['delta']['type']
            ) {
                $signature = $data['delta']['signature'] ?? '';
                $currentThinkingSignature = ($currentThinkingSignature ?? '').$signature;
                yield new ThinkingSignature($signature);
                continue;
            }

            // Handle tool_use content block start
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

            // Handle tool_use input JSON deltas
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

            // Handle content block stop - finalize current thinking or tool call
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

            // Handle message stop - yield tool calls if any were collected
            if ('message_stop' === $type) {
                $inMessage = false;

                if ('max_tokens' === $stopReason) {
                    $message = 'Anthropic truncated the response after reaching the output token limit. Raise the output token budget (max_tokens) or reduce the request scope.';
                    if (null !== $outputTokens) {
                        $message = \sprintf('Anthropic truncated the response after reaching the maximum of %d output tokens. Raise the output token budget (max_tokens) or reduce the request scope.', $outputTokens);
                    }

                    throw new MaxOutputTokensException($message);
                }

                if ([] !== $toolCalls) {
                    yield new ToolCallComplete($toolCalls);
                }
            }
        }

        if ($inMessage) {
            throw new IncompleteStreamException('Anthropic stream ended before message_stop.');
        }
    }
}
