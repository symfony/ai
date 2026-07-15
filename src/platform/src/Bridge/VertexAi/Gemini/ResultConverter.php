<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini\FinishReasonMapper;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
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
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @phpstan-type Part array{
 *     functionCall?: array{id?: string, name: string, args: mixed[]},
 *     text?: string,
 *     thought?: bool,
 *     thoughtSignature?: string,
 *     inlineData?: array{data: string, mimeType: string},
 *     executableCode?: array{id?: string, language: string, code: string},
 *     codeExecutionResult?: array{id?: string, outcome: self::OUTCOME_*, output: string},
 * }
 *
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use FinishReasonAwareTrait;

    public const OUTCOME_OK = 'OUTCOME_OK';
    public const OUTCOME_FAILED = 'OUTCOME_FAILED';
    public const OUTCOME_DEADLINE_EXCEEDED = 'OUTCOME_DEADLINE_EXCEEDED';

    public function supports(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (429 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;
            throw new RateLimitExceededException(null, $errorMessage);
        }

        if (400 === $response->getStatusCode()) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? null;

            if (null !== $errorMessage
                && (str_contains($errorMessage, 'maximum number of tokens') || str_contains($errorMessage, 'input token count'))
            ) {
                throw new ExceedContextSizeException($errorMessage);
            }
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

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error from Gemini API: "%s"', $data['error']['message'] ?? 'Unknown error'), $data['error']['code']);
        }

        if (!isset($data['candidates'][0]['content']['parts'][0])) {
            // Gemini can return a well-formed completion with a terminal finish reason but no
            // content parts (e.g. an empty message after a tool result). Treat it as empty text
            // instead of crashing on an otherwise valid response.
            if (isset($data['candidates'][0]['finishReason'])) {
                return $this->withFinishReason(
                    new TextResult(''),
                    FinishReasonMapper::map($data['candidates'][0]['finishReason']),
                );
            }

            throw new RuntimeException('Response does not contain any content.');
        }

        $choices = array_map($this->convertChoice(...), $data['candidates']);

        return $this->withFinishReason(
            1 === \count($choices) ? $choices[0] : new ChoiceResult($choices),
            FinishReasonMapper::map($data['candidates'][0]['finishReason'] ?? null),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function convertStream(RawResultInterface $result): \Generator
    {
        $finishReason = null;
        // Thinking boundary state, carried across chunks: Gemini streams thought parts (often split
        // over several chunks) before the answer, so a thinking block may span multiple iterations.
        $thinking = null;
        $thinkingSignature = null;

        foreach ($result->getDataStream() as $data) {
            if (isset($data['usageMetadata']['totalTokenCount']) && 0 < $data['usageMetadata']['totalTokenCount']) {
                yield $this->getTokenUsageExtractor()->fromUsageMetadata($data['usageMetadata']);
            }

            // Gemini repeats the reason on every candidate of the terminal chunk; the leading one wins,
            // matching the buffered path.
            if (null !== ($data['candidates'][0]['finishReason'] ?? null)) {
                $finishReason ??= FinishReasonMapper::map($data['candidates'][0]['finishReason']);
            }

            $choices = array_values(array_filter(array_map($this->convertChoice(...), $data['candidates'] ?? [])));

            if (!$choices) {
                continue;
            }

            // The multi-candidate path is exotic for Gemini; preserve its bare-delta behavior.
            if (1 !== \count($choices)) {
                $deltas = [];
                foreach ($choices as $choice) {
                    $deltas = array_merge($deltas, iterator_to_array($this->resultToDeltas($choice), false));
                }

                if ([] !== $deltas) {
                    yield new ChoiceDelta($deltas);
                }

                continue;
            }

            // A single candidate may carry multiple parts (e.g. a thought part plus text, or a tool
            // call plus text) that convertChoice() returns as a MultiPartResult; flatten to leaves so
            // thought and non-thought parts are framed identically whether combined in one chunk or
            // split across chunks.
            foreach ($this->flattenResult($choices[0]) as $leaf) {
                if ($leaf instanceof ThinkingResult) {
                    if (null === $thinking) {
                        yield new ThinkingStart();
                        $thinking = '';
                    }

                    $content = $leaf->getContent() ?? '';
                    $thinking .= $content;

                    if (null !== $leaf->getSignature()) {
                        $thinkingSignature = $leaf->getSignature();
                    }

                    yield new ThinkingDelta($content);

                    continue;
                }

                // The first non-thinking part closes an open thinking block.
                if (null !== $thinking) {
                    yield new ThinkingComplete($thinking, $thinkingSignature);
                    $thinking = null;
                    $thinkingSignature = null;
                }

                yield from $this->resultToDeltas($leaf);
            }
        }

        // A thinking block still open at the end of the stream is completed before the terminal metadata.
        if (null !== $thinking) {
            yield new ThinkingComplete($thinking, $thinkingSignature);
        }

        // Emitted last: the terminal chunk carries both the finish reason and its content parts.
        if (null !== $finishReason) {
            yield new MetadataDelta('finish_reason', $finishReason);
        }
    }

    /**
     * Flattens a single choice into its leaf results so the streaming thinking-boundary logic can walk
     * thought and non-thought parts uniformly, whether they arrive combined in one MultiPartResult
     * chunk or split across chunks.
     *
     * @return list<ResultInterface>
     */
    private function flattenResult(ResultInterface $result): array
    {
        if (!$result instanceof MultiPartResult) {
            return [$result];
        }

        $leaves = [];
        foreach ($result->getContent() as $part) {
            foreach ($this->flattenResult($part) as $leaf) {
                $leaves[] = $leaf;
            }
        }

        return $leaves;
    }

    /**
     * ExecutableCodeResult and CodeExecutionResult have no streaming delta representation and are
     * only exposed through the buffered result; they are skipped here instead of crashing.
     *
     * @return \Generator<DeltaInterface>
     */
    private function resultToDeltas(ResultInterface $result): \Generator
    {
        switch (true) {
            case $result instanceof MultiPartResult:
                foreach ($result->getContent() as $part) {
                    yield from $this->resultToDeltas($part);
                }

                return;
            case $result instanceof ThinkingResult:
                yield new ThinkingDelta($result->getContent() ?? '');

                return;
            case $result instanceof TextResult:
                yield new TextDelta($result->getContent());

                return;
            case $result instanceof BinaryResult:
                yield new BinaryDelta($result->getContent(), $result->getMimeType());

                return;
            case $result instanceof ToolCallResult:
                yield new ToolCallComplete($result->getContent());

                return;
        }
    }

    /**
     * @param array{
     *     finishReason?: string,
     *     content?: array{
     *         role: 'model',
     *         parts: list<Part>
     *     }
     * } $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult|ThinkingResult|BinaryResult|ExecutableCodeResult|CodeExecutionResult|MultiPartResult|null
    {
        if (!isset($choice['content']['parts'])) {
            return null;
        }

        $contentParts = $choice['content']['parts'];

        return match (\count($contentParts)) {
            1 => $this->convertPart($contentParts[0]),
            default => new MultiPartResult(array_values(array_filter(array_map($this->convertPart(...), $contentParts)))),
        };
    }

    /**
     * @param Part $contentPart
     */
    private function convertPart(array $contentPart): ToolCallResult|TextResult|ThinkingResult|BinaryResult|ExecutableCodeResult|CodeExecutionResult|null
    {
        $signature = $contentPart['thoughtSignature'] ?? null;

        return match (true) {
            isset($contentPart['functionCall']) => new ToolCallResult([$this->convertToolCall($contentPart['functionCall'], $signature)]),
            true === ($contentPart['thought'] ?? false) => new ThinkingResult($contentPart['text'] ?? '', $signature),
            isset($contentPart['text']) => new TextResult($contentPart['text'], $signature),
            isset($contentPart['inlineData']) => BinaryResult::fromBase64($contentPart['inlineData']['data'], $contentPart['inlineData']['mimeType'] ?? null),
            isset($contentPart['executableCode']) => new ExecutableCodeResult(
                $contentPart['executableCode']['code'],
                $contentPart['executableCode']['language'],
                $contentPart['executableCode']['id'] ?? null,
            ),
            isset($contentPart['codeExecutionResult']) => new CodeExecutionResult(
                self::OUTCOME_OK === $contentPart['codeExecutionResult']['outcome'],
                $contentPart['codeExecutionResult']['output'],
                $contentPart['codeExecutionResult']['id'] ?? null,
            ),
            default => null,
        };
    }

    /**
     * @param array{
     *     id?: string,
     *     name: string,
     *     args: mixed[]
     * } $toolCall
     */
    private function convertToolCall(array $toolCall, ?string $signature = null): ToolCall
    {
        return new ToolCall($toolCall['id'] ?? '', $toolCall['name'], $this->normalizeArguments($toolCall['args']), $signature);
    }

    /**
     * Gemini emits empty strings for optional object properties it has no value for, whereas other
     * providers omit them or send null. Coerce those empty strings to null (recursing into nested
     * structures) so downstream denormalization — e.g. of nullable DateTime properties — behaves
     * consistently across bridges. List elements (integer-keyed) are left untouched, as an empty
     * string can be a legitimate value inside a list argument.
     *
     * @param mixed[] $arguments
     *
     * @return mixed[]
     */
    private function normalizeArguments(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (\is_array($value)) {
                $arguments[$key] = $this->normalizeArguments($value);
            } elseif ('' === $value && !\is_int($key)) {
                $arguments[$key] = null;
            }
        }

        return $arguments;
    }
}
