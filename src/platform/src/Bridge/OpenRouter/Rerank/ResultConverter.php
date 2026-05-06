<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Rerank;

use Symfony\AI\Platform\Bridge\OpenRouter\RerankModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof RerankModel;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): RerankingResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['results'])) {
            throw new RuntimeException('Response does not contain reranking results.');
        }

        return new RerankingResult(
            array_map(
                static fn (array $item): RerankingEntry => new RerankingEntry((int) $item['index'], (float) $item['relevance_score']),
                $data['results'],
            ),
        );
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
