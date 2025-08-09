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
        private string $hostUrl,
        private string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ElevenLabs;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return match ($model->getName()) {
            ElevenLabs::SPEECH_TO_TEXT => $this->doSpeechToTextRequest($model, $payload, $options),
            ElevenLabs::TEXT_TO_SPEECH => $this->doTextToSpeechRequest($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported.', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doSpeechToTextRequest(Model $model, array|string $payload, array $options): RawHttpResult
    {
        if (!\array_key_exists('model', $model->getOptions())) {
            throw new InvalidArgumentException('The model option is required.');
        }

        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload must be an array, received "%s".', get_debug_type($payload)));
        }

        $model = $options['model'] ??= $model->getOptions()['model'];

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/speech-to-text', $this->hostUrl), [
            'headers' => [
                'xi-api-key' => $this->apiKey,
            ],
            'body' => [
                'file' => fopen($payload['input_audio']['path'], 'r'),
                'model_id' => $model,
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doTextToSpeechRequest(Model $model, array|string $payload, array $options): RawHttpResult
    {
        if (!\array_key_exists('model', $model->getOptions())) {
            throw new InvalidArgumentException('The model option is required.');
        }

        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload must be an array, received "%s".', get_debug_type($payload)));
        }

        if (!\array_key_exists('text', $payload)) {
            throw new InvalidArgumentException('The payload must contain a "text" key.');
        }

        $model = $options['model'] ??= $model->getOptions()['model'];

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/text-to-speech/%s', $this->hostUrl, $model), [
            'headers' => [
                'xi-api-key' => $this->apiKey,
            ],
            'json' => [
                'text' => $payload['text'],
                'model_id' => 'eleven_multilingual_v2',
            ],
        ]));
    }
}
