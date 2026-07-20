<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks;

use Symfony\AI\Platform\Bridge\Generic\Completions\CompletionsConversionTrait;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FireworksResultConverter implements ResultConverterInterface
{
    use CompletionsConversionTrait;
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Fireworks;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $url = null;

        if ($result instanceof RawHttpResult) {
            $response = $result->getObject();

            if (400 === $response->getStatusCode()) {
                $body = json_decode($response->getContent(false), true) ?? [];
                $code = $body['error']['code'] ?? $body['code'] ?? null;
                $message = $body['error']['message'] ?? $body['message'] ?? '';

                // Fireworks tags context overflows with a "context_length_exceeded" code, but the
                // "invalid_request_error" fallback also carries the limit in the message.
                if ('context_length_exceeded' === $code || str_contains($message, 'context length') || str_contains($message, 'context_length')) {
                    throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
                }
            }

            $this->throwOnHttpError($response);
            $url = $response->getInfo('url');
        }

        // Streaming is only supported for chat completions.
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        if (null !== $url && str_contains($url, '/workflows/')) {
            return $this->convertImageResult($result);
        }

        if (null !== $url && str_contains($url, '/rerank')) {
            return $this->convertRerankResult($result);
        }

        if (null !== $url && str_contains($url, '/embeddings')) {
            return $this->convertEmbeddingsResult($result);
        }

        return $this->convertCompletionsResult($result);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string, mixed> $usage
     */
    protected function convertStreamUsage(array $usage): TokenUsage
    {
        return $this->getTokenUsageExtractor()->extractFromArray($usage);
    }

    private function convertCompletionsResult(RawResultInterface $result): ResultInterface
    {
        $data = $result->getData();

        if (isset($data['error']['code'])) {
            match ($data['error']['code']) {
                'content_filter' => throw new ContentFilterException($data['error']['message']),
                'invalid_request_error' => throw new InvalidRequestException($data['error']['message']),
                default => throw new RuntimeException($data['error']['message']),
            };
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    private function convertEmbeddingsResult(RawResultInterface $result): VectorResult
    {
        $data = $result->getData();

        if (!isset($data['data'][0]['embedding'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $item): Vector => new Vector($item['embedding']),
                $data['data'],
            ),
        );
    }

    private function convertRerankResult(RawResultInterface $result): RerankingResult
    {
        $data = $result->getData();

        if (!isset($data['data'])) {
            throw new RuntimeException('Response does not contain reranking results.');
        }

        return new RerankingResult(
            array_map(
                static fn (array $item): RerankingEntry => new RerankingEntry((int) $item['index'], (float) $item['relevance_score']),
                $data['data'],
            ),
        );
    }

    private function convertImageResult(RawResultInterface $result): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['base64']) || [] === $data['base64']) {
            throw new RuntimeException('Response does not contain generated images.');
        }

        $images = array_map(
            static function (string $dataUri): BinaryResult {
                if (1 === preg_match('#^data:(?P<mime>[^;]+);base64,(?P<data>.+)$#', $dataUri, $matches)) {
                    return BinaryResult::fromBase64($matches['data'], $matches['mime']);
                }

                return BinaryResult::fromBase64($dataUri);
            },
            $data['base64'],
        );

        return 1 === \count($images) ? $images[0] : new MultiPartResult($images);
    }
}
