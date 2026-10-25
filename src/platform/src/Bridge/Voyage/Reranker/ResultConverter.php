<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Reranker;

use Symfony\AI\Platform\Bridge\Voyage\Reranker;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Reranker;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): RerankingResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

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

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * Overrides the shared lookup: Voyage reports errors as `{"detail": "..."}`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $detail = $response->toArray(false)['detail'] ?? null;
        } catch (DecodingExceptionInterface) {
            return null;
        }

        return \is_string($detail) ? $detail : null;
    }
}
