<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together;

use Symfony\AI\Platform\Bridge\Generic\Completions\CompletionsConversionTrait;
use Symfony\AI\Platform\Bridge\Generic\Completions\TokenUsageExtractor;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TogetherResultConverter implements ResultConverterInterface
{
    use CompletionsConversionTrait {
        CompletionsConversionTrait::convertChoice as private doConvertChoice;
    }
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Together;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $url = '';
        if ($result instanceof RawHttpResult) {
            $url = $this->getResponseUrl($result);

            // Audio speech returns raw binary audio and must not be JSON-decoded.
            if (str_contains($url, '/audio/speech')) {
                return $this->convertSpeech($result, $options);
            }
        }

        $data = $result->getData();

        if (isset($data['error'])) {
            $this->throwOnApiError($data['error']);
        }

        return match (true) {
            str_contains($url, '/images/generations') => $this->convertImage($data, $options),
            str_contains($url, '/audio/transcriptions'), str_contains($url, '/audio/translations') => $this->convertTranscription($data),
            str_contains($url, '/rerank') => $this->convertRerank($data),
            str_contains($url, '/embeddings') => $this->convertEmbeddings($data['data'] ?? null),
            default => $this->convertCompletion($data),
        };
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    private function throwOnApiError(mixed $error): never
    {
        $message = 'Unknown error';
        $code = null;

        if (\is_array($error)) {
            if (isset($error['message']) && \is_string($error['message'])) {
                $message = $error['message'];
            }

            if (isset($error['code']) && \is_string($error['code'])) {
                $code = $error['code'];
            } elseif (isset($error['type']) && \is_string($error['type'])) {
                $code = $error['type'];
            }
        }

        if ('content_filter' === $code) {
            throw new ContentFilterException($message);
        }

        if ('invalid_request_error' === $code) {
            throw new InvalidRequestException($message);
        }

        throw new RuntimeException($message);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertCompletion(array $data): ResultInterface
    {
        if (!isset($data['choices']) || !\is_array($data['choices']) || [] === $data['choices']) {
            throw new RuntimeException('Result does not contain choices.');
        }

        /** @var list<array{index: int, message: array{role: 'assistant', content: ?string, tool_calls: list<array{id: string, type: 'function', function: array{name: string, arguments: string}}>, refusal: ?mixed}, logprobs: string, finish_reason: 'stop'|'eos'|'length'|'tool_calls'|'content_filter'}> $rawChoices */
        $rawChoices = $data['choices'];

        $choices = array_map($this->convertChoice(...), $rawChoices);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    /**
     * Together uses the additional "eos" finish reason for completions that naturally
     * reached an end-of-sequence token, which is equivalent to "stop".
     *
     * @param array{index: int, message: array{role: 'assistant', content: ?string, tool_calls: list<array{id: string, type: 'function', function: array{name: string, arguments: string}}>, refusal: ?mixed}, logprobs: string, finish_reason: 'stop'|'eos'|'length'|'tool_calls'|'content_filter'} $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult
    {
        if ('eos' === $choice['finish_reason']) {
            $choice['finish_reason'] = 'stop';
        }

        return $this->doConvertChoice($choice);
    }

    private function convertEmbeddings(mixed $data): VectorResult
    {
        if (!\is_array($data) || [] === $data) {
            throw new RuntimeException('Response does not contain embeddings.');
        }

        $vectors = [];

        foreach ($data as $item) {
            if (!\is_array($item) || !isset($item['embedding']) || !\is_array($item['embedding'])) {
                throw new RuntimeException('Response does not contain a valid "embedding" key.');
            }

            /** @var list<float> $embedding */
            $embedding = $item['embedding'];

            $vectors[] = new Vector($embedding);
        }

        return new VectorResult($vectors);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    private function convertImage(array $data, array $options): BinaryResult
    {
        if (!isset($data['data']) || !\is_array($data['data']) || !isset($data['data'][0]) || !\is_array($data['data'][0])) {
            throw new RuntimeException('Response does not contain generated image data.');
        }

        $image = $data['data'][0];

        if (!isset($image['b64_json']) || !\is_string($image['b64_json'])) {
            throw new RuntimeException('Response does not contain base64-encoded image data, use the "base64" response format.');
        }

        // The Together API defaults the image "output_format" to jpeg.
        $mimeType = 'png' === ($options['output_format'] ?? 'jpeg') ? 'image/png' : 'image/jpeg';

        return BinaryResult::fromBase64($image['b64_json'], $mimeType);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function convertSpeech(RawHttpResult $result, array $options): BinaryResult
    {
        $mimeType = match ($options['response_format'] ?? 'wav') {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => 'application/octet-stream',
        };

        return new BinaryResult($result->getObject()->getContent(), $mimeType);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertTranscription(array $data): TextResult
    {
        if (!isset($data['text']) || !\is_string($data['text'])) {
            throw new RuntimeException('Response does not contain a transcription.');
        }

        return new TextResult($data['text']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertRerank(array $data): RerankingResult
    {
        if (!isset($data['results']) || !\is_array($data['results'])) {
            throw new RuntimeException('Response does not contain reranking results.');
        }

        $entries = [];

        foreach ($data['results'] as $item) {
            if (!\is_array($item) || !isset($item['index'], $item['relevance_score'])) {
                continue;
            }

            if (!is_numeric($item['index']) || !is_numeric($item['relevance_score'])) {
                continue;
            }

            $entries[] = new RerankingEntry((int) $item['index'], (float) $item['relevance_score']);
        }

        return new RerankingResult($entries);
    }

    private function getResponseUrl(RawHttpResult $result): string
    {
        $url = $result->getObject()->getInfo('url');

        return \is_string($url) ? $url : '';
    }
}
