<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TransportInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Ollama `/api/embed` contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbedClient implements EndpointClientInterface
{
    public const ENDPOINT = 'ollama.embed';

    private const TOP_LEVEL_KEYS = [
        'truncate',
        'keep_alive',
        'dimensions',
    ];

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
        $options = self::splitOptions($options, self::TOP_LEVEL_KEYS);

        $envelope = new RequestEnvelope(
            payload: array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
            path: '/api/embed',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): VectorResult
    {
        $data = $raw->getData();

        if (!isset($data['embeddings']) || [] === $data['embeddings']) {
            throw new RuntimeException('Response does not contain embeddings.');
        }

        return new VectorResult(array_map(
            static fn (array $embedding): Vector => new Vector($embedding),
            $data['embeddings'],
        ));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string>         $topLevelKeys
     *
     * @return array<string, mixed>
     */
    private static function splitOptions(array $options, array $topLevelKeys): array
    {
        $topLevelOptions = [];
        $nested = $options['options'] ?? [];

        foreach ($options as $key => $value) {
            if ('options' === $key) {
                continue;
            }
            if (\in_array($key, $topLevelKeys, true)) {
                $topLevelOptions[$key] = $value;
            } else {
                $nested[$key] ??= $value;
            }
        }

        if ([] !== $nested) {
            $topLevelOptions['options'] = $nested;
        }

        return $topLevelOptions;
    }
}
