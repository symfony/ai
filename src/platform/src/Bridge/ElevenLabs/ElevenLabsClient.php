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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class ElevenLabsClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $hostUrl = 'https://api.elevenlabs.io/v1',
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

        if (\in_array($model->getName(), [ElevenLabs::SCRIBE_V1, ElevenLabs::SCRIBE_V1_EXPERIMENTAL], true)) {
            return $this->doSpeechToTextRequest($model, $payload);
        }

        $capabilities = $this->retrieveCapabilities($model);

        if (!$capabilities['can_do_text_to_speech']) {
            throw new InvalidArgumentException(\sprintf('The model "%s" does not support text-to-speech.', $model->getName()));
        }

        return $this->doTextToSpeechRequest($model, $payload, $options);
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
        if (!\array_key_exists('voice', $model->getOptions())) {
            throw new InvalidArgumentException('The voice option is required.');
        }

        if (!\array_key_exists('text', $payload)) {
            throw new InvalidArgumentException('The payload must contain a "text" key.');
        }

        $voice = $options['voice'] ??= $model->getOptions()['voice'];
        $stream = $options['stream'] ??= $model->getOptions()['stream'] ?? false;

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

    /**
     * @return array<string, mixed>
     */
    private function retrieveCapabilities(Model $model): array
    {
        $capabilityResponse = $this->httpClient->request('GET', \sprintf('%s/models', $this->hostUrl), [
            'headers' => [
                'xi-api-key' => $this->apiKey,
            ],
        ]);

        $models = $capabilityResponse->toArray();

        $currentModelConfiguration = array_filter($models, static fn (array $information): bool => $information['model_id'] === $model->getName());

        if ([] === $currentModelConfiguration) {
            throw new InvalidArgumentException('The model information could not be retrieved from the ElevenLabs API. Your model might not be supported. Try to use another one.');
        }

        return reset($currentModelConfiguration);
    }
}
