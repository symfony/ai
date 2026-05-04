<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * Cartesia /stt contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SpeechToTextClient implements EndpointClientInterface
{
    public const ENDPOINT = 'cartesia.speech_to_text';

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
        $envelope = new RequestEnvelope(
            payload: [
                ...$options,
                'model' => $model->getName(),
                'file' => fopen($payload['input_audio']['path'], 'r'),
                'timestamp_granularities[]' => 'word',
            ],
            headers: ['Content-Type' => 'multipart/form-data'],
            path: '/stt',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): TextResult
    {
        return new TextResult($raw->getData()['text']);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
