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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TransportInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Cohere /v2/embed contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbedClient implements EndpointClientInterface
{
    public const ENDPOINT = 'cohere.embed';

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
        $texts = \is_array($payload) ? $payload : [$payload];

        $body = [
            'model' => $model->getName(),
            'texts' => $texts,
            'input_type' => ($options['input_type'] ?? $model->getOptions()['input_type'] ?? InputType::SearchDocument)->value,
        ];

        if (isset($options['embedding_types'])) {
            $body['embedding_types'] = $options['embedding_types'];
        }

        $envelope = new RequestEnvelope(payload: $body, path: '/v2/embed');

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): VectorResult
    {
        $data = $raw->getData();

        if (!isset($data['embeddings']['float'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(array_map(
            static fn (array $embedding): Vector => new Vector($embedding),
            $data['embeddings']['float'],
        ));
    }

    public function getTokenUsageExtractor(): MetaBilledUnitsTokenUsageExtractor
    {
        return new MetaBilledUnitsTokenUsageExtractor();
    }
}
