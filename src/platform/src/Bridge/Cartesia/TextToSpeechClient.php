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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * Cartesia /tts/bytes contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextToSpeechClient implements EndpointClientInterface
{
    public const ENDPOINT = 'cartesia.text_to_speech';

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
        $text = \is_string($payload) ? $payload : ($payload['text'] ?? throw new RuntimeException('The payload must contain a "text" key.'));

        $envelope = new RequestEnvelope(
            payload: [
                ...$options,
                'model_id' => $model->getName(),
                'transcript' => $text,
                'voice' => ['mode' => 'id', 'id' => $options['voice']],
                'output_format' => $options['output_format'],
            ],
            path: '/tts/bytes',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): BinaryResult
    {
        $bytes = $raw instanceof RawHttpResult ? $raw->getObject()->getContent() : (string) ($raw->getData()[0] ?? '');

        return new BinaryResult($bytes, 'audio/mpeg');
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
