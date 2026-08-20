<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Llm;

use Symfony\AI\Platform\Bridge\Generic\Completions\CompletionsConversionTrait;
use Symfony\AI\Platform\Bridge\Generic\Completions\FinishReasonMapper;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    use CompletionsConversionTrait;
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Mistral;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $httpResponse = $result->getObject();

        if (400 === $httpResponse->getStatusCode()) {
            $body = json_decode($httpResponse->getContent(false), true) ?? [];
            $code = $body['error']['code'] ?? $body['code'] ?? null;
            $message = $body['error']['message'] ?? $body['message'] ?? '';

            if ('context_length_exceeded' === $code || str_contains($message, 'maximum context length')) {
                throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
            }
        }

        $this->throwOnHttpError($httpResponse);

        if (($code = $httpResponse->getStatusCode()) >= 400) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $code, $httpResponse->getContent(false)));
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (!isset($data['choices'])) {
            throw new RuntimeException('Response does not contain choices.');
        }

        $choices = array_map($this->convertReasoningChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * With `reasoning_effort` enabled, Mistral sends `delta.content` as a list of thinking and text
     * chunks until the thinking block closes, and as a plain string afterwards.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function normalizeStreamChunk(array $data): array
    {
        if (!\is_array($data['choices'][0]['delta']['content'] ?? null)) {
            return $data;
        }

        [$thinking, $text] = $this->flattenContentChunks($data['choices'][0]['delta']['content']);

        unset($data['choices'][0]['delta']['content']);

        if ('' !== $thinking) {
            $data['choices'][0]['delta']['reasoning_content'] = $thinking;
        }

        if ('' !== $text) {
            $data['choices'][0]['delta']['content'] = $text;
        }

        return $data;
    }

    /**
     * With `reasoning_effort` enabled, `message.content` carries the same chunk list as the stream,
     * so the thinking trace becomes its own part next to the answer.
     *
     * @param array<string, mixed> $choice
     */
    private function convertReasoningChoice(array $choice): ResultInterface
    {
        if (!\is_array($choice['message']['content'] ?? null)) {
            return $this->convertChoice($choice);
        }

        [$thinking, $text, $signature] = $this->flattenContentChunks($choice['message']['content']);

        $choice['message']['content'] = $text;

        if ('' === $thinking) {
            return $this->convertChoice($choice);
        }

        $thinkingResult = new ThinkingResult($thinking, $signature);

        if ('' === $text && 'tool_calls' !== $choice['finish_reason']) {
            return $this->withFinishReason($thinkingResult, FinishReasonMapper::map($choice['finish_reason']));
        }

        return $this->withFinishReason(
            new MultiPartResult([$thinkingResult, $this->convertChoice($choice)]),
            FinishReasonMapper::map($choice['finish_reason']),
        );
    }

    /**
     * @param list<array{type?: string, text?: string, thinking?: list<array{text?: string}>, signature?: ?string}> $content
     *
     * @return array{string, string, ?string}
     */
    private function flattenContentChunks(array $content): array
    {
        $thinking = '';
        $text = '';
        $signature = null;

        foreach ($content as $chunk) {
            if ('thinking' === ($chunk['type'] ?? null)) {
                foreach (\is_array($chunk['thinking'] ?? null) ? $chunk['thinking'] : [] as $part) {
                    $thinking .= $part['text'] ?? '';
                }

                $signature ??= $chunk['signature'] ?? null;

                continue;
            }

            $text .= $chunk['text'] ?? '';
        }

        return [$thinking, $text, $signature];
    }
}
