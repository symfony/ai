<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

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
final class DeepgramClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Deepgram;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $deepgramPayload = new DeepgramPayload($payload);

        return match (true) {
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $deepgramPayload, $options),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $deepgramPayload, $options),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Deepgram API.', $model->getName())),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doTextToSpeech(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'speak', [
            'query' => [
                'model' => $model->getName(),
                ...$options,
            ],
            'json' => [
                'text' => $payload->asTextToSpeechPayload(),
            ],
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doSpeechToText(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        $query = [
            'model' => $model->getName(),
            ...$options,
        ];

        if ($payload->isUrlBased()) {
            return new RawHttpResult($this->httpClient->request('POST', 'listen', [
                'query' => $query,
                'json' => [
                    'url' => $payload->getAudioUrl(),
                ],
            ]));
        }

        return new RawHttpResult($this->httpClient->request('POST', 'listen', [
            'query' => $query,
            'headers' => [
                'Content-Type' => $payload->getAudioMimeType(),
            ],
            'body' => $payload->getAudioBinary(),
        ]));
    }
}
