<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Speech;

use Symfony\AI\Platform\Bridge\OpenRouter\SpeechModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for Speech requests.');
        }

        if (\is_string($payload)) {
            $input = $payload;
        } elseif (isset($payload['text'])) {
            $input = $payload['text'];
        } else {
            throw new InvalidArgumentException('The payload must be a string or an array with a "text" key.');
        }

        $body = [
            'model' => $model->getName(),
            'input' => $input,
            'voice' => $options['voice'],
        ];

        if (isset($options['response_format'])) {
            $body['response_format'] = $options['response_format'];
        }

        if (isset($options['speed'])) {
            $body['speed'] = $options['speed'];
        }

        if (isset($options['provider'])) {
            $body['provider'] = $options['provider'];
        }

        return new RawHttpResult($this->httpClient->request('POST', 'https://openrouter.ai/api/v1/audio/speech', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]));
    }
}
