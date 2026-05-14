<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Google AI Studio (`generativelanguage.googleapis.com`) embeddings via
 * `batchEmbedContents`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchEmbedContentsClient implements EndpointClientInterface
{
    public const ENDPOINT = 'google.batch_embed_contents';

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

        $requests = [];
        foreach ($texts as $text) {
            $requests[] = array_filter([
                'model' => 'models/'.$model->getName(),
                'content' => ['parts' => [['text' => $text]]],
                'outputDimensionality' => $modelOptions['dimensions'] ?? null,
                'taskType' => $modelOptions['task_type'] ?? null,
                'title' => $options['title'] ?? null,
            ]);
        }

        $envelope = new RequestEnvelope(
            payload: ['requests' => $requests],
            path: \sprintf('models/%s:batchEmbedContents', $model->getName()),
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): VectorResult
    {
        $data = $raw->getData();

        if (!isset($data['embeddings'])) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(array_map(
            static fn (array $item): Vector => new Vector($item['values']),
            $data['embeddings'],
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
