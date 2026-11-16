<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;

use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeech;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (($options['stream'] ?? false) || isset($options['stream_format'])) {
            throw new InvalidArgumentException('Streaming text to speech results is not supported yet.');
        }

        unset($options['stream'], $options['stream_format']);

        if (\is_string($payload)) {
            $input = $payload;
        } elseif (!\array_key_exists('text', $payload)) {
            throw new InvalidArgumentException('The payload must contain a "text" key.');
        } else {
            $input = $payload['text'];
        }

        if (!\is_string($input)) {
            throw new InvalidArgumentException('The payload "text" key must be a string.');
        }

        return new RawHttpResult($this->httpClient->request('POST', '/v1/audio/speech', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody(array_merge($options, [
                'model' => $model->getName(),
                'input' => $input,
            ])),
        ]));
    }
}
