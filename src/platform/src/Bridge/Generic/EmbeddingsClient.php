<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic;

use Symfony\AI\Platform\Bridge\Generic\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TransportInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * OpenAI-compatible `/v1/embeddings` contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsClient implements EndpointClientInterface
{
    public const ENDPOINT = 'openai_compatible.embeddings';

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $path = '/v1/embeddings',
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
        $envelope = new RequestEnvelope(
            payload: array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
            path: $this->path,
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): VectorResult
    {
        $data = $raw->getData();

        if (!isset($data['data'][0]['embedding'])) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(array_map(
            static fn (array $item): Vector => new Vector($item['embedding']),
            $data['data'],
        ));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }
}
