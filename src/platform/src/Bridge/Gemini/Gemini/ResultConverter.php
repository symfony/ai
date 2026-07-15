<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ChoiceDelta;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @phpstan-type Part array{
 *     functionCall?: array{id?: string, name: string, args: mixed[]},
 *     text?: string,
 *     thought?: bool,
 *     thoughtSignature?: string,
 *     inlineData?: array{data: string, mimeType: string},
 *     executableCode?: array{language: string, code: string},
 *     codeExecutionResult?: array{id?: string, outcome: self::OUTCOME_*, output: string},
 * }
 *
 * @author Roy Garrido
 */
final class ResultConverter implements ResultConverterInterface
{
    use FinishReasonAwareTrait;

    use HttpStatusErrorHandlingTrait;

    public const OUTCOME_OK = 'OUTCOME_OK';
    public const OUTCOME_FAILED = 'OUTCOME_FAILED';
    public const OUTCOME_DEADLINE_EXCEEDED = 'OUTCOME_DEADLINE_EXCEEDED';

    public function supports(Model $model): bool
    {
        return $model instanceof Gemini;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (400 === $response->getStatusCode()) {
            $message = json_decode($response->getContent(false), true)['error']['message'] ?? '';

            if (str_contains($message, 'maximum number of tokens') || str_contains($message, 'input token count')) {
                throw new ExceedContextSizeException($message);
            }
        }

        $this->throwOnHttpError($response);

        if ($options['stream'] ?? false) {
            if (($code = $response->getStatusCode()) >= 400) {
                throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $response->getContent(false)));
            }

            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (!isset($data['candidates'][0]['content']['parts'][0])) {
            if (isset($data['error'])) {
                throw new RuntimeException(\sprintf('Error "%s" - "%s": "%s".', $data['error']['code'], $data['error']['status'], $data['error']['message']));
            }

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

    private function convertStream(RawResultInterface $result): \Generator
    {
        $finishReason = null;

        foreach ($result->getDataStream() as $data) {
            // Gemini repeats the reason on every candidate of the terminal chunk; the leading one wins,
            // matching the buffered path.
            if (null !== ($data['candidates'][0]['finishReason'] ?? null)) {
                $finishReason ??= FinishReasonMapper::map($data['candidates'][0]['finishReason']);
            }

            $choices = array_values(array_filter(array_map($this->convertChoice(...), $data['candidates'] ?? [])));

            if (!$choices) {
                continue;
            }

            if (1 !== \count($choices)) {
                yield new ChoiceDelta(array_map($this->resultToDelta(...), $choices));
                continue;
            }

            yield $this->resultToDelta($choices[0]);
        }

        // Emitted last: the terminal chunk carries both the finish reason and its content parts.
        if (null !== $finishReason) {
            yield new MetadataDelta('finish_reason', $finishReason);
        }
    }

    private function resultToDelta(ToolCallResult|TextResult|BinaryResult $result): DeltaInterface
    {
        return match (true) {
            $result instanceof TextResult => new TextDelta($result->getContent()),
            $result instanceof BinaryResult => new BinaryDelta($result->getContent(), $result->getMimeType()),
            $result instanceof ToolCallResult => new ToolCallComplete($result->getContent()),
        };
    }

    /**
     * @param array{
     *     finishReason?: string,
     *     content?: array{
     *         parts: list<Part>
     *     }
     * } $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult|BinaryResult|ExecutableCodeResult|CodeExecutionResult|MultiPartResult|null
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
