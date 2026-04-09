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
        $payload = new DeepgramPayload($payload);

        return match (true) {
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $payload, $options),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Deepgram API.', $model->getName())),
        };
    }

    private function doTextToSpeech(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'speak', [
            'query' => [
                'model' => $model->getName(),
            ],
            'json' => [
                ...$options,
                'text' => $payload->asTextToSpeechPayload(),
            ],
        ]));
    }

    private function doSpeechToText(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'listen', [
            'json' => [
                ...$options,
                'model' => $model->getName(),
                'url' => $payload->asSpeechToTextPayload(),
            ],
        ]));
    }
}
