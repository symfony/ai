<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\SpeechToText;

use Symfony\AI\Platform\Bridge\Ovh\SpeechToText;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://oai.endpoints.kepler.ai.cloud.ovh.net/doc/whisper-large-v3/openapi.json
 *
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://oai.endpoints.kepler.ai.cloud.ovh.net',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $body = array_merge($options, $payload, ['model' => $model->getName()]);

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/audio/transcriptions', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => $body,
        ]));
    }
}
