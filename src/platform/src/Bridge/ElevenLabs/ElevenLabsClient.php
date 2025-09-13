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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabsClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ElevenLabs;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload must be an array, received "%s".', get_debug_type($payload)));
        }

        return match (true) {
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToTextRequest($model, $payload),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeechRequest($model, $payload, [
                ...$options,
                ...$model->getOptions(),
            ]),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" does not support text-to-speech or speech-to-text, please check the model information.', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     */
    private function doSpeechToTextRequest(Model $model, array|string $payload): RawHttpResult
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array for speech-to-text request, got "%s".', \gettype($payload)));
        }

        if (!\array_key_exists('input_audio', $payload)) {
            throw new InvalidArgumentException('Input audio is required for speech-to-text request.');
        }

        if (!\is_array($payload['input_audio'])) {
            throw new InvalidArgumentException('Input audio must be an array with a "path" key for speech-to-text request.');
        }

        return new RawHttpResult($this->httpClient->request('POST', 'speech-to-text', [
            'body' => [
                'file' => fopen($payload['input_audio']['path'], 'r'),
                'model_id' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doTextToSpeechRequest(Model $model, array|string $payload, array $options): RawHttpResult
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array for text-to-speech request, got "%s".', \gettype($payload)));
        }

        if (!\array_key_exists('voice', $options)) {
            throw new InvalidArgumentException('The voice option is required.');
        }

        if (!\array_key_exists('text', $payload)) {
            throw new InvalidArgumentException('The payload must contain a "text" key.');
        }

        $voice = $model->getOptions()['voice'] ?? $options['voice'] ?? throw new InvalidArgumentException('The voice option is required.');
        $stream = $options['stream'] ?? false;

        $url = $stream
            ? \sprintf('text-to-speech/%s/stream', $voice)
            : \sprintf('text-to-speech/%s', $voice);

        unset($options['voice'], $options['stream']);

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'json' => [
                'text' => $payload['text'],
                'model_id' => $model->getName(),
                ...$options,
            ],
        ]));
    }
}
