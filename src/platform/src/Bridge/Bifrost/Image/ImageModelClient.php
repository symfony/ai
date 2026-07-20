<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Image;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bifrost image-generation client. Issues `POST /v1/images/generations`
 * (relative path, resolved by the scoped HTTP client built by the Factory)
 * with an OpenAI-compatible JSON payload routed by Bifrost to the
 * underlying provider (OpenAI DALL-E, GPT-Image, Google Imagen, …).
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageModelClient implements ModelClientInterface
{
    private const PATH = '/v1/images/generations';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ImageModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            $prompt = $payload;
        } elseif (isset($payload['prompt']) && \is_string($payload['prompt'])) {
            $prompt = $payload['prompt'];
        } else {
            throw new InvalidArgumentException('The payload must be a string or contain a string "prompt" key.');
        }

        return new RawHttpResult($this->httpClient->request('POST', self::PATH, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'prompt' => $prompt,
            ]),
        ]));
    }
}
