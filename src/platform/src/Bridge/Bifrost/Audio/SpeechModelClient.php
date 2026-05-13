<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Audio;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bifrost text-to-speech client. Issues `POST /v1/audio/speech` (relative
 * path, resolved by the scoped HTTP client built by the Factory) with an
 * OpenAI-compatible JSON payload routed by Bifrost to the underlying
 * provider (OpenAI, ElevenLabs, …).
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechModelClient implements ModelClientInterface
{
    private const PATH = '/v1/audio/speech';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for text-to-speech requests.');
        }

        if (isset($options['stream']) || isset($options['stream_format'])) {
            throw new InvalidArgumentException('Streaming text-to-speech results is not supported yet.');
        }

        if (\is_string($payload)) {
            $input = $payload;
        } elseif (isset($payload['text']) && \is_string($payload['text'])) {
            $input = $payload['text'];
        } else {
            throw new InvalidArgumentException('The payload must be a string or contain a string "text" key.');
        }

        return new RawHttpResult($this->httpClient->request('POST', self::PATH, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $input,
            ]),
        ]));
    }
}
