<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TaskType;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TransportInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Vertex AI embeddings via `predict`. Distinct payload (`instances`) and
 * response (`predictions[].embeddings.values`) shape from Google AI Studio's
 * `batchEmbedContents` — kept in the Vertex AI bridge for that reason.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PredictEmbeddingsClient implements EndpointClientInterface
{
    public const ENDPOINT = 'google.predict_embeddings';

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
        $modelOptions = $model->getOptions();
        $texts = \is_array($payload) ? $payload : [$payload];

        $body = [
            'instances' => array_map(
                static fn (string $text) => [
                    'content' => $text,
                    'title' => $options['title'] ?? null,
                    'task_type' => $modelOptions['task_type'] ?? TaskType::RETRIEVAL_QUERY,
                ],
                $texts,
            ),
        ];

        unset($modelOptions['task_type']);

        $envelope = new RequestEnvelope(
            payload: array_merge($body, $modelOptions),
            path: \sprintf('models/%s:predict', $model->getName()),
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): VectorResult
    {
        $data = $raw->getData();

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error from Embeddings API: "%s"', $data['error']['message'] ?? 'Unknown error'));
        }

        if (!isset($data['predictions'])) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(array_map(
            static fn (array $item): Vector => new Vector($item['embeddings']['values']),
            $data['predictions'],
        ));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }
}
