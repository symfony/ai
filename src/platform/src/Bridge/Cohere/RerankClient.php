<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\TransportInterface;

/**
 * Cohere /v2/rerank contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RerankClient implements EndpointClientInterface
{
    public const ENDPOINT = 'cohere.rerank';

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_array($payload) || !isset($payload['query'], $payload['texts'])) {
            throw new InvalidArgumentException('Reranker payload must be an array with "query" and "texts" keys.');
        }

        $body = [
            'model' => $model->getName(),
            'query' => $payload['query'],
            'documents' => $payload['texts'],
        ];

        if (isset($options['top_n'])) {
            $body['top_n'] = $options['top_n'];
        }

        $envelope = new RequestEnvelope(payload: $body, path: '/v2/rerank');

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): RerankingResult
    {
        $data = $raw->getData();

        if (!isset($data['results'])) {
            throw new RuntimeException('Response does not contain reranking results.');
        }

        return new RerankingResult(array_map(
            static fn (array $item): RerankingEntry => new RerankingEntry((int) $item['index'], (float) $item['relevance_score']),
            $data['results'],
        ));
    }

    public function getTokenUsageExtractor(): MetaBilledUnitsTokenUsageExtractor
    {
        return new MetaBilledUnitsTokenUsageExtractor();
    }
}
