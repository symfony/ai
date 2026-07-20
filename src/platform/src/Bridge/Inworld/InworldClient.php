<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Inworld;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\NdjsonStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InworldClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Inworld;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return match (true) {
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $payload, [
                ...$options,
                ...$model->getOptions(),
            ]),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $payload, [
                ...$options,
                ...$model->getOptions(),
            ]),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" does not support text-to-speech or speech-to-text, please check the model information.', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doTextToSpeech(Model $model, array|string $payload, array $options): RawHttpResult
    {
        $voice = $options['voice'] ?? throw new InvalidArgumentException('The voice option is required.');

        if (!\is_string($voice)) {
            throw new InvalidArgumentException('The voice option must be a string.');
        }

        if (\is_string($payload)) {
            $text = $payload;
        } else {
            $rawText = $payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.');

            if (!\is_string($rawText)) {
                throw new InvalidArgumentException('The "text" key of the payload must be a string.');
            }

            $text = $rawText;
        }

        $stream = ($options['stream'] ?? false) === true;
        $audioConfig = $options['audioConfig'] ?? [
            'audioEncoding' => 'MP3',
            'sampleRateHertz' => 48000,
        ];

        unset($options['voice'], $options['stream'], $options['audioConfig']);

        $body = [
            'text' => $text,
            'voiceId' => $voice,
            'modelId' => $model->getName(),
            'audioConfig' => $audioConfig,
            ...$options,
        ];

        $url = $stream ? 'tts/v1/voice:stream' : 'tts/v1/voice';

        $response = $this->httpClient->request('POST', $url, [
            'json' => $body,
        ]);

        if ($stream) {
            return new RawHttpResult($response, new NdjsonStream());
        }

        return new RawHttpResult($response);
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doSpeechToText(Model $model, array|string $payload, array $options): RawHttpResult
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array for speech-to-text request, got "%s".', \gettype($payload)));
        }

        if (!isset($payload['input_audio']) || !\is_array($payload['input_audio'])) {
            throw new InvalidArgumentException('Input audio is required for speech-to-text request.');
        }

        $data = $payload['input_audio']['data'] ?? null;

        if (!\is_string($data) || '' === $data) {
            throw new InvalidArgumentException('The "input_audio" entry must contain a non-empty base64 "data" key.');
        }

        $audioEncoding = $options['audioEncoding'] ?? 'AUTO_DETECT';

        unset($options['audioEncoding']);

        $body = [
            'transcribeConfig' => [
                'modelId' => $model->getName(),
                'audioEncoding' => $audioEncoding,
                ...$options,
            ],
            'audioData' => [
                'content' => $data,
            ],
        ];

        return new RawHttpResult($this->httpClient->request('POST', 'stt/v1/transcribe', [
            'json' => $body,
        ]));
    }
}
