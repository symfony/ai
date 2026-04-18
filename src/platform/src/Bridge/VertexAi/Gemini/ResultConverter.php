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

use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
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
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
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
            throw new RateLimitExceededException();
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error from Gemini API: "%s"', $data['error']['message'] ?? 'Unknown error'), $data['error']['code']);
        }

        if (!isset($data['candidates'][0]['content']['parts'][0])) {
            throw new RuntimeException('Response does not contain any content.');
        }

        $choices = array_map($this->convertChoice(...), $data['candidates']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
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
        foreach ($result->getDataStream() as $data) {
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
     *         role: 'model',
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
        return new ToolCall($toolCall['id'] ?? '', $toolCall['name'], $toolCall['args'], $signature);
    }
}
