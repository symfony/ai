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
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\MaxOutputTokensException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ComputerCallResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\FileSearchResult;
use Symfony\AI\Platform\Result\LocalShellCallResult;
use Symfony\AI\Platform\Result\McpApprovalRequestResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\McpListToolsResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
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
use Symfony\AI\Platform\Result\WebSearchResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type OutputMessage array{content: array<Refusal|OutputText>, id: string, role: string, type: 'message'}
 * @phpstan-type OutputText array{type: 'output_text', text: string}
 * @phpstan-type Refusal array{type: 'refusal', refusal: string}
 * @phpstan-type FunctionCall array{id?: string|null, arguments: string, call_id?: string|null, name: string, type: 'function_call'}
 * @phpstan-type Thinking array{summary: list<array{type: string, text?: string}>, id: string}
 * @phpstan-type Error array{code?: string|null, type?: string|null, param?: string|null, message?: string|null}
 * @phpstan-type WebSearchCall array{type: 'web_search_call', id?: string, status?: string, action?: array{type?: string, query?: string, queries?: list<string>}}
 * @phpstan-type FileSearchCall array{type: 'file_search_call', id?: string, status?: string, queries?: list<string>, results?: list<array<string, mixed>>|null}
 * @phpstan-type CodeInterpreterCall array{type: 'code_interpreter_call', id?: string, status?: string, code?: string|null, outputs?: list<array{type?: string, logs?: string, url?: string}>|null}
 * @phpstan-type ImageGenerationCall array{type: 'image_generation_call', id?: string, status?: string, result?: string|null}
 * @phpstan-type McpCall array{type: 'mcp_call', id?: string, status?: string, server_label?: string, name?: string, arguments?: string, output?: string|null, error?: string|null}
 * @phpstan-type McpListTools array{type: 'mcp_list_tools', id?: string, server_label?: string, tools?: list<array<string, mixed>>}
 * @phpstan-type McpApprovalRequest array{type: 'mcp_approval_request', id?: string, server_label?: string, name?: string, arguments?: string}
 * @phpstan-type ComputerCall array{type: 'computer_call', id?: string, status?: string, call_id?: string, action?: array<string, mixed>, pending_safety_checks?: list<array{id: string, code?: string|null, message?: string|null}>}
 * @phpstan-type LocalShellCall array{type: 'local_shell_call', id?: string, status?: string, call_id?: string, action?: array{type?: string, command?: list<string>}}
 * @phpstan-type OutputItem OutputMessage|FunctionCall|Thinking|WebSearchCall|FileSearchCall|CodeInterpreterCall|ImageGenerationCall|McpCall|McpListTools|McpApprovalRequest|ComputerCall|LocalShellCall
 */
class ResultConverter implements ResultConverterInterface
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
            throw new RateLimitExceededException($this->extractRateLimitReset($response), $errorMessage);
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

                if ('max_output_tokens' === $reason) {
                    throw new MaxOutputTokensException('Responses API truncated the response after reaching the output token limit. Raise the output token budget (max_output_tokens) or reduce the request scope.');
                }

                throw new RuntimeException(\sprintf('Responses API response is incomplete (%s) and contains no content.', $reason));
            }

            throw new RuntimeException('Response does not contain any content.');
        }

        $converted = 1 === \count($results) ? array_pop($results) : new MultiPartResult(array_values($results));

        return $this->withResponsesFinishReason($converted, $data['incomplete_details']['reason'] ?? $data['status'] ?? null);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * Resolves the rate-limit reset delay (in seconds) from a 429 response.
     *
     * The generic Responses API does not expose a reset time; provider-specific
     * bridges can override this to parse their own rate-limit headers.
     */
    protected function extractRateLimitReset(ResponseInterface $response): ?int
    {
        return null;
    }

    /**
     * The Responses API has no per-choice `finish_reason`: a successful response reports `status: completed`
     * and an aborted one `status: incomplete` plus an `incomplete_details.reason` such as `max_output_tokens`.
     *
     * It also reports `completed` when the model stopped to call a function, so the normalized case is derived
     * from the converted output while the raw provider value is preserved as reported.
     *
     * @template T of ResultInterface
     *
     * @param T $result
     *
     * @return T
     */
    private function withResponsesFinishReason(ResultInterface $result, ?string $rawFinishReason): ResultInterface
    {
        if (null === $rawFinishReason || '' === $rawFinishReason) {
            return $result;
        }

        $result->getMetadata()->add('finish_reason', FinishReasonMapper::map($rawFinishReason, $this->containsToolCall($result)));

        return $result;
    }

    private function containsToolCall(ResultInterface $result): bool
    {
        if ($result instanceof ToolCallResult) {
            return true;
        }

        if (!$result instanceof MultiPartResult) {
            return false;
        }

        foreach ($result->getContent() as $part) {
            if ($part instanceof ToolCallResult) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<OutputItem> $output
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
     * @param OutputItem $item
     *
     * @return iterable<ResultInterface>
     */
    private function processOutputItem(array $item): iterable
    {
        $type = $item['type'] ?? null;

        // Built-in server-side tool calls (web search, file search, code
        // interpreter, image generation, computer use, local shell, hosted MCP)
        // are reported as their own output items next to the assistant message.
        // Convert them into typed results so consumers can introspect what the
        // model did, while the message item is still converted as usual.
        return match ($type) {
            'message' => $this->convertOutputMessage($item),
            'reasoning' => $this->convertReasoning($item),
            'web_search_call' => $this->convertWebSearchCall($item),
            'file_search_call' => $this->convertFileSearchCall($item),
            'code_interpreter_call' => $this->convertCodeInterpreterCall($item),
            'image_generation_call' => $this->convertImageGenerationCall($item),
            'computer_call' => $this->convertComputerCall($item),
            'local_shell_call' => $this->convertLocalShellCall($item),
            'mcp_call' => $this->convertMcpCall($item),
            'mcp_list_tools' => $this->convertMcpListTools($item),
            'mcp_approval_request' => $this->convertMcpApprovalRequest($item),
            default => throw new RuntimeException(\sprintf('Unsupported output type "%s".', $type)),
        };
    }

    /**
     * @param WebSearchCall $item
     *
     * @return list<WebSearchResult>
     */
    private function convertWebSearchCall(array $item): array
    {
        $action = $item['action'] ?? [];
        $queries = $action['queries'] ?? [];
        $query = $action['query'] ?? ($queries[0] ?? null);

        return [new WebSearchResult($query, $item['id'] ?? null, $item['status'] ?? null, $queries)];
    }

    /**
     * @param FileSearchCall $item
     *
     * @return list<FileSearchResult>
     */
    private function convertFileSearchCall(array $item): array
    {
        return [new FileSearchResult(
            array_values($item['queries'] ?? []),
            array_values($item['results'] ?? []),
            $item['id'] ?? null,
            $item['status'] ?? null,
        )];
    }

    /**
     * @param CodeInterpreterCall $item
     *
     * @return list<ExecutableCodeResult|CodeExecutionResult>
     */
    private function convertCodeInterpreterCall(array $item): array
    {
        $id = $item['id'] ?? null;
        $results = [new ExecutableCodeResult($item['code'] ?? '', 'python', $id)];

        $outputs = $item['outputs'] ?? null;
        if (null !== $outputs && [] !== $outputs) {
            $logs = '';
            foreach ($outputs as $output) {
                if ('logs' === ($output['type'] ?? null) && isset($output['logs'])) {
                    $logs .= $output['logs'];
                }
            }

            $results[] = new CodeExecutionResult('failed' !== ($item['status'] ?? null), '' !== $logs ? $logs : null, $id);
        }

        return $results;
    }

    /**
     * @param ImageGenerationCall $item
     *
     * @return list<BinaryResult>
     */
    private function convertImageGenerationCall(array $item): array
    {
        $result = $item['result'] ?? null;
        if (null === $result || '' === $result) {
            return [];
        }

        return [BinaryResult::fromBase64($result, 'image/png')];
    }

    /**
     * @param ComputerCall $item
     *
     * @return list<ComputerCallResult>
     */
    private function convertComputerCall(array $item): array
    {
        return [new ComputerCallResult(
            $item['action'] ?? [],
            $item['call_id'] ?? null,
            array_values($item['pending_safety_checks'] ?? []),
            $item['id'] ?? null,
            $item['status'] ?? null,
        )];
    }

    /**
     * @param LocalShellCall $item
     *
     * @return list<LocalShellCallResult>
     */
    private function convertLocalShellCall(array $item): array
    {
        return [new LocalShellCallResult(
            array_values($item['action']['command'] ?? []),
            $item['call_id'] ?? null,
            $item['id'] ?? null,
            $item['status'] ?? null,
        )];
    }

    /**
     * @param McpCall $item
     *
     * @return list<McpCallResult>
     */
    private function convertMcpCall(array $item): array
    {
        return [new McpCallResult(
            $item['server_label'] ?? '',
            $item['name'] ?? '',
            $item['arguments'] ?? null,
            $item['output'] ?? null,
            $item['error'] ?? null,
            $item['id'] ?? null,
            $item['status'] ?? null,
        )];
    }

    /**
     * @param McpListTools $item
     *
     * @return list<McpListToolsResult>
     */
    private function convertMcpListTools(array $item): array
    {
        return [new McpListToolsResult(
            $item['server_label'] ?? '',
            array_values($item['tools'] ?? []),
            $item['id'] ?? null,
        )];
    }

    /**
     * @param McpApprovalRequest $item
     *
     * @return list<McpApprovalRequestResult>
     */
    private function convertMcpApprovalRequest(array $item): array
    {
        return [new McpApprovalRequestResult(
            $item['server_label'] ?? '',
            $item['name'] ?? '',
            $item['arguments'] ?? null,
            $item['id'] ?? null,
        )];
    }

    private function convertStream(RawResultInterface|RawHttpResult $result): \Generator
    {
        $currentThinking = null;
        /** @var array<string, ToolCall> $toolCalls */
        $toolCalls = [];
        $sawResponseEvent = false;
        $sawResponseCompleted = false;
        $sawToolCallComplete = false;

        foreach ($result->getDataStream() as $event) {
            $type = $event['type'] ?? '';
            $sawResponseEvent = true;

            if ('error' === $type) {
                $error = $this->extractStreamError($event);
                $message = $this->generateErrorMessage($error);

                if ($this->isRateLimitError($error)) {
                    throw new RateLimitExceededException(null, $message);
                }

                if ($this->isServerError($error)) {
                    throw new ServerException(null, $message);
                }

                throw new RuntimeException($message);
            }

            if ('response.failed' === $type) {
                $response = \is_array($event['response'] ?? null) ? $event['response'] : [];
                $error = $this->extractStreamError($response);
                $message = $this->generateErrorMessage($error);

                if ($this->isRateLimitError($error)) {
                    throw new RateLimitExceededException(null, $message);
                }

                if ($this->isServerError($error)) {
                    throw new ServerException(null, $message);
                }

                throw new RuntimeException($message);
            }

            if ('response.incomplete' === $type) {
                $reason = $event['response']['incomplete_details']['reason'] ?? 'unknown';
                if (!\is_string($reason) || '' === $reason) {
                    $reason = 'unknown';
                }

                if ('max_output_tokens' === $reason) {
                    throw new MaxOutputTokensException('Responses API truncated the response after reaching the output token limit. Raise the output token budget (max_output_tokens) or reduce the request scope.');
                }

                throw new RuntimeException(\sprintf('Responses API response is incomplete (%s).', $reason));
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
                $sawToolCallComplete = true;
                yield new ToolCallComplete($toolCallResult->getContent());
            } elseif ([] !== $toolCalls) {
                $sawToolCallComplete = true;
                yield new ToolCallComplete(array_values($toolCalls));
            }
        }

        if ($sawResponseEvent && !$sawResponseCompleted) {
            throw new IncompleteStreamException('Responses API stream ended before response.completed.');
        }

        // A stream that reaches response.completed always finished cleanly: response.incomplete and
        // response.failed throw above. Only the tool-call case needs to be told apart from a plain stop.
        if ($sawResponseCompleted) {
            yield new MetadataDelta('finish_reason', FinishReasonMapper::map('completed', $sawToolCallComplete));
        }
    }

    /**
     * @param array<OutputItem> $output
     *
     * @return list<ToolCallResult|array<OutputItem>|null>
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
     * @throws MalformedToolCallException
     */
    private function convertFunctionCall(array $toolCall): ToolCall
    {
        try {
            $arguments = json_decode($toolCall['arguments'], true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MalformedToolCallException(\sprintf('OpenResponses returned malformed JSON arguments for the "%s" tool: "%s"', $toolCall['name'], $e->getMessage()), 0, $e);
        }

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
     * @param Error $error
     */
    private function isRateLimitError(array $error): bool
    {
        return \in_array($error['code'], ['rate_limit_exceeded', 'rate_limit_error', 'too_many_requests'], true)
            || \in_array($error['type'], ['rate_limit_exceeded', 'rate_limit_error', 'too_many_requests'], true);
    }

    /**
     * @param Error $error
     */
    private function isServerError(array $error): bool
    {
        return \in_array($error['code'], ['server_error', 'internal_error'], true)
            || \in_array($error['type'], ['server_error', 'internal_error'], true);
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
