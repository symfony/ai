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
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
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

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult(...$choices);
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function convertStream(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $data) {
            $choices = array_map($this->convertChoice(...), $data['candidates'] ?? []);

            if (!$choices) {
                continue;
            }

            if (1 !== \count($choices)) {
                yield new ChoiceResult(...$choices);
                continue;
            }

            yield $choices[0]->getContent();
        }
    }

    /**
     * @param array{
     *     finishReason?: string,
     *     content: array{
     *         role: 'model',
     *         parts: array{
     *             functionCall?: array{
     *                 name: string,
     *                 args: mixed[]
     *             },
     *             text?: string
     *         }[]
     *     }
     * } $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult|BinaryResult
    {
        $contentParts = $choice['content']['parts'];

        // If any part is a function call, return it immediately and ignore all other parts.
        foreach ($contentParts as $contentPart) {
            if (isset($contentPart['functionCall'])) {
                return new ToolCallResult($this->convertToolCall($contentPart['functionCall']));
            }
        }

        if (1 === \count($contentParts)) {
            $contentPart = $contentParts[0];

            if (isset($contentPart['text'])) {
                return new TextResult($contentPart['text']);
            }

            if (isset($contentPart['inlineData'])) {
                return new BinaryResult($contentPart['inlineData']['data'], $contentPart['inlineData']['mimeType'] ?? null);
            }

            throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $choice['finishReason']));
        }

        $content = '';
        $successfulCodeExecutionDetected = false;
        foreach ($contentParts as $contentPart) {
            if ($this->isSuccessfulCodeExecution($contentPart)) {
                $successfulCodeExecutionDetected = true;
                continue;
            }

            if ($successfulCodeExecutionDetected) {
                $content .= $contentPart['text'];
            }
        }

        if ('' !== $content) {
            return new TextResult($content);
        }

        throw new RuntimeException('Code execution failed.');
    }

    /**
     * @param array{
     *     name: string,
     *     args: mixed[]
     * } $toolCall
     */
    private function convertToolCall(array $toolCall): ToolCall
    {
        return new ToolCall($toolCall['name'], $toolCall['name'], $toolCall['args']);
    }

    /**
     * @param array{
     *     codeExecutionResult?: array{
     *         outcome: self::OUTCOME_*,
     *         output: string
     *     }
     * } $contentPart
     */
    private function isSuccessfulCodeExecution(array $contentPart): bool
    {
        if (!isset($contentPart['codeExecutionResult'])) {
            return false;
        }

        $result = $contentPart['codeExecutionResult'];

        return self::OUTCOME_OK === $result['outcome'];
    }
}
