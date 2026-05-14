<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ElevenLabs text-to-speech contract handler.
 *
 * Path encodes the voice; `?stream=true` switches to the streaming variant.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextToSpeechClient implements EndpointClientInterface
{
    public const ENDPOINT = 'elevenlabs.text_to_speech';

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly HttpClientInterface $httpClient,
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
        $options = [...$options, ...$model->getOptions()];

        if (!\array_key_exists('voice', $options)) {
            throw new InvalidArgumentException('The voice option is required.');
        }

        $text = \is_string($payload) ? $payload : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        $voice = $model->getOptions()['voice'] ?? $options['voice'];
        $stream = $options['stream'] ?? false;

        $path = $stream
            ? \sprintf('text-to-speech/%s/stream', $voice)
            : \sprintf('text-to-speech/%s', $voice);

        unset($options['voice'], $options['stream']);

        $envelope = new RequestEnvelope(
            payload: ['text' => $text, 'model_id' => $model->getName(), ...$options],
            path: $path,
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if (!$raw instanceof RawHttpResult) {
            throw new RuntimeException(\sprintf('"%s" requires an HTTP-backed raw result, got "%s".', self::class, $raw::class));
        }

        $response = $raw->getObject();

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertToGenerator($response));
        }

        return new BinaryResult($response->getContent(), 'audio/mpeg');
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    private function convertToGenerator(ResponseInterface $response): \Generator
    {
        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            if ('' === $chunk->getContent()) {
                continue;
            }

            yield new BinaryDelta($chunk->getContent());
        }
    }
}
