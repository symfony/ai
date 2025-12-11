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
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $hostUrl = 'https://api.elevenlabs.io/v1',
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

        if ($model->supports(Capability::SPEECH_TO_TEXT)) {
            return $this->doSpeechToTextRequest($model, $payload);
        }

        if ($model->supports(Capability::TEXT_TO_SPEECH)) {
            return $this->doTextToSpeechRequest($model, $payload, [
                ...$options,
                ...$model->getOptions(),
            ]);
        }

        throw new InvalidArgumentException(\sprintf('The model "%s" does not support text-to-speech or speech-to-text, please check the model information.', $model->getName()));
    }

    /**
     * @param array<string|int, mixed> $payload
     */
    private function doSpeechToTextRequest(Model $model, array|string $payload): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/speech-to-text', $this->hostUrl), [
            'headers' => [
                'xi-api-key' => $this->apiKey,
            ],
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
        if (!\array_key_exists('voice', $options)) {
            throw new InvalidArgumentException('The voice option is required.');
        }

        if (!\array_key_exists('text', $payload)) {
            throw new InvalidArgumentException('The payload must contain a "text" key.');
        }

        $voice = $options['voice'];
        $stream = $options['stream'] ?? false;

        $url = $stream
            ? \sprintf('%s/text-to-speech/%s/stream', $this->hostUrl, $voice)
            : \sprintf('%s/text-to-speech/%s', $this->hostUrl, $voice);

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'xi-api-key' => $this->apiKey,
            ],
            'json' => [
                'text' => $payload['text'],
                'model_id' => $model->getName(),
            ],
        ]));
    }
}
