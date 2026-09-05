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
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\MaxOutputTokensException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingSignature;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\Stream\Delta\WebSearchComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\WebSearchResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ResultConverter implements ResultConverterInterface
{
    use FinishReasonAwareTrait;

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
        /** @var array<string, array{index: int, blocks: list<array<string, mixed>>}> $webSearchCalls */
        $webSearchCalls = [];
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
                } elseif ('web_search' === $content['name']) {
                    $query = \is_string($content['input']['query'] ?? null) ? $content['input']['query'] : null;
                    $id = \is_string($content['id'] ?? null) ? $content['id'] : null;

                    if (null !== $id) {
                        $webSearchCalls[$id] = ['index' => \count($results), 'blocks' => [$content]];
                    }

                    $results[] = new WebSearchResult(
                        $query,
                        $id,
                        queries: null === $query ? [] : [$query],
                        signature: self::encodeWebSearchBlocks([$content]),
                    );
                }
            } elseif ('bash_code_execution_tool_result' === $content['type']) {
                $results[] = new CodeExecutionResult(
                    0 === ($content['content']['return_code'] ?? 0),
                    ($content['content']['stdout'] ?? '').($content['content']['stderr'] ?? '') ?: null,
                    $content['tool_use_id'],
                );
            } elseif ('text_editor_code_execution_tool_result' === $content['type']) {
                $results[] = new CodeExecutionResult(true, null, $content['tool_use_id']);
            } elseif ('web_search_tool_result' === $content['type']) {
                // Neither result type reports the individual hits, so only the status survives the
                // conversion; the blocks themselves travel on in the signature, because Anthropic
                // rejects a replayed `web_search_tool_result` without its `server_tool_use` call.
                $id = \is_string($content['tool_use_id'] ?? null) ? $content['tool_use_id'] : null;
                $status = self::webSearchStatus($content);
                $call = null === $id ? null : ($webSearchCalls[$id] ?? null);

                if (null !== $call) {
                    $previous = $results[$call['index']];
                    \assert($previous instanceof WebSearchResult);
                    $blocks = [...$call['blocks'], $content];
                    $webSearchCalls[$id]['blocks'] = $blocks;
                    $results[$call['index']] = new WebSearchResult(
                        $previous->getQuery(),
                        $previous->getId(),
                        $status,
                        $previous->getQueries(),
                        self::encodeWebSearchBlocks($blocks),
                    );
                } else {
                    $results[] = new WebSearchResult(
                        id: $id,
                        status: $status,
                        signature: self::encodeWebSearchBlocks([$content]),
                    );
                }
            } elseif ('thinking' === $content['type']) {
                $results[] = new ThinkingResult($content['thinking'], $content['signature'] ?? null);
            }
        }

        if ([] === $results) {
            throw new RuntimeException('Response content does not contain any supported content.');
        }

        return $this->withFinishReason(
            1 === \count($results) ? $results[0] : new MultiPartResult($results),
            FinishReasonMapper::map($data['stop_reason'] ?? null),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * The replay payload of one hosted web search: the `server_tool_use` call and its
     * `web_search_tool_result`, in the order Anthropic sent them. Anthropic rejects either block
     * without the other on the next turn, so both travel as the result's signature.
     *
     * @param list<array<string, mixed>> $blocks
     */
    private static function encodeWebSearchBlocks(array $blocks): string
    {
        return json_encode($blocks, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array{id: string|null, query: string|null, queries: list<string>, blocks: list<array<string, mixed>>} $call
     * @param array<string, mixed>|null                                                                             $result
     */
    private static function webSearchComplete(array $call, ?array $result = null, ?string $status = null): WebSearchComplete
    {
        $blocks = $call['blocks'];
        if (null !== $result) {
            $blocks[] = $result;
        }

        return new WebSearchComplete(new WebSearchResult(
            $call['query'],
            $call['id'],
            $status,
            $call['queries'],
            self::encodeWebSearchBlocks($blocks),
        ));
    }

    /**
     * Success carries a list of `web_search_result` blocks; an error is a single
     * `web_search_tool_result_error` object reporting an `error_code` instead.
     *
     * @param array<string, mixed> $content
     */
    private static function webSearchStatus(array $content): ?string
    {
        if ('web_search_tool_result_error' !== ($content['content']['type'] ?? null)) {
            return 'completed';
        }

        return \is_string($content['content']['error_code'] ?? null) ? $content['content']['error_code'] : null;
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        $currentToolCall = null;
        $currentToolCallJson = '';
        /** @var array{id: string|null, block: array<string, mixed>}|null $currentWebSearch */
        $currentWebSearch = null;
        $currentWebSearchJson = '';
        /** @var array{id: string|null, query: string|null, queries: list<string>, blocks: list<array<string, mixed>>}|null $pendingWebSearch */
        $pendingWebSearch = null;
        $currentThinking = null;
        $currentThinkingSignature = null;
        $inMessage = false;
        $stopReason = null;
        $outputTokens = null;
        // Only message_start names the model; carry it so the message_delta usage reports it too.
        $model = null;

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
                $model = $data['message']['model'] ?? null;
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
                yield $this->getTokenUsageExtractor()->extractFromArray($usage, $model);
            }

            if ('message_delta' === $type) {
                $stopReason = $data['delta']['stop_reason'] ?? $stopReason;

                if (isset($data['usage'])) {
                    $outputTokens = $data['usage']['output_tokens'] ?? $outputTokens;
                    yield $this->getTokenUsageExtractor()->extractFromArray([
                        'output_tokens' => $outputTokens ?? 0,
                    ], $model);
                }
            }

            // Handle text content deltas
            if ('content_block_delta' === $type && isset($data['delta']['text'])) {
                yield new TextDelta($data['delta']['text']);
                continue;
            }

            // A closed `server_tool_use` call waits for its `web_search_tool_result` so both blocks
            // reach the assistant turn as one search; anything else starting means none is coming.
            if ('content_block_start' === $type
                && null !== $pendingWebSearch
                && 'web_search_tool_result' !== ($data['content_block']['type'] ?? null)
            ) {
                yield self::webSearchComplete($pendingWebSearch);
                $pendingWebSearch = null;
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

            // Handle provider-hosted web search content block start
            if ('content_block_start' === $type
                && 'server_tool_use' === ($data['content_block']['type'] ?? null)
                && 'web_search' === ($data['content_block']['name'] ?? null)
            ) {
                $id = \is_string($data['content_block']['id'] ?? null) ? $data['content_block']['id'] : null;
                $currentWebSearch = [
                    'id' => $id,
                    'block' => $data['content_block'],
                ];
                $currentWebSearchJson = '';
                continue;
            }

            if ('content_block_start' === $type
                && 'web_search_tool_result' === ($data['content_block']['type'] ?? null)
            ) {
                $id = \is_string($data['content_block']['tool_use_id'] ?? null) ? $data['content_block']['tool_use_id'] : null;
                $status = self::webSearchStatus($data['content_block']);

                if (null !== $pendingWebSearch && (null === $id || $pendingWebSearch['id'] === $id)) {
                    yield self::webSearchComplete($pendingWebSearch, $data['content_block'], $status);
                    $pendingWebSearch = null;

                    continue;
                }

                if (null !== $pendingWebSearch) {
                    yield self::webSearchComplete($pendingWebSearch);
                    $pendingWebSearch = null;
                }

                yield new WebSearchComplete(new WebSearchResult(
                    id: $id,
                    status: $status,
                    signature: self::encodeWebSearchBlocks([$data['content_block']]),
                ));
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
                if (null !== $currentToolCall) {
                    $currentToolCallJson .= $partialJson;
                    yield new ToolInputDelta($currentToolCall['id'], $currentToolCall['name'], $partialJson);
                } elseif (null !== $currentWebSearch) {
                    $currentWebSearchJson .= $partialJson;
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
                    $input = [];
                    if ('' !== $currentToolCallJson) {
                        try {
                            $input = json_decode($currentToolCallJson, true, flags: \JSON_THROW_ON_ERROR);
                        } catch (\JsonException $e) {
                            throw new MalformedToolCallException(\sprintf('Anthropic returned malformed JSON arguments for the "%s" tool: "%s"', $currentToolCall['name'], $e->getMessage()), 0, $e);
                        }
                    }
                    $toolCalls[] = new ToolCall(
                        $currentToolCall['id'],
                        $currentToolCall['name'],
                        $input
                    );
                    $currentToolCall = null;
                    $currentToolCallJson = '';
                    continue;
                }

                if (null !== $currentWebSearch) {
                    $input = \is_array($currentWebSearch['block']['input'] ?? null) ? $currentWebSearch['block']['input'] : [];
                    if ('' !== $currentWebSearchJson) {
                        try {
                            $input = json_decode($currentWebSearchJson, true, flags: \JSON_THROW_ON_ERROR);
                        } catch (\JsonException $e) {
                            throw new MalformedToolCallException(\sprintf('Anthropic returned malformed JSON arguments for the "web_search" tool: "%s"', $e->getMessage()), 0, $e);
                        }
                    }
                    $query = \is_string($input['query'] ?? null) ? $input['query'] : null;
                    $currentWebSearch['block']['input'] = \is_array($input) ? $input : [];

                    $pendingWebSearch = [
                        'id' => $currentWebSearch['id'],
                        'query' => $query,
                        'queries' => null === $query ? [] : [$query],
                        'blocks' => [$currentWebSearch['block']],
                    ];
                    $currentWebSearch = null;
                    $currentWebSearchJson = '';
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

                // A turn stopping on `pause_turn` ends after the call block, its result arriving
                // only on the next request, so the call replays on its own.
                if (null !== $pendingWebSearch) {
                    yield self::webSearchComplete($pendingWebSearch);
                    $pendingWebSearch = null;
                }

                if ([] !== $toolCalls) {
                    yield new ToolCallComplete($toolCalls);
                }
            }
        }

        if ($inMessage) {
            throw new IncompleteStreamException('Anthropic stream ended before message_stop.');
        }

        // Anthropic reports the stop reason on message_delta, before message_stop. A `max_tokens`
        // truncation has already thrown above, so any reason reaching here is a normal completion.
        if (null !== $stopReason) {
            yield new MetadataDelta('finish_reason', FinishReasonMapper::map($stopReason));
        }
    }
}
