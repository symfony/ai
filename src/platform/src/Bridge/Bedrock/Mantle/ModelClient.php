<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle;

use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI-compatible HTTP client for the AWS Bedrock Mantle endpoint, shared by the Chat Completions
 * and Responses routes (the request path and supported model type are configurable).
 *
 * Requests can be authenticated either with a Bedrock API key sent as a bearer token (recommended)
 * or with AWS SigV4 signing using the standard credential chain. When an API key is provided it
 * takes precedence over SigV4.
 *
 * @author asrar <aszenz@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    private const MODEL_PATHS = [
        'google.gemma-4-31b' => '/openai/v1/chat/completions',
    ];

    private readonly EventSourceHttpClient $httpClient;
    private readonly ?SigV4RequestSigner $requestSigner;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        string $region,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        ?CredentialProvider $credentialProvider = null,
        private readonly string $path = '/v1/chat/completions',
        private readonly string $supportedModel = CompletionsModel::class,
    ) {
        if (null === $apiKey) {
            $this->requestSigner = new SigV4RequestSigner($region, $credentialProvider ?? ChainProvider::createDefaultChain($httpClient));
        } else {
            $this->requestSigner = null;
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof $this->supportedModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $data = \is_array($payload) ? array_merge($options, ['model' => $model->getName()], $payload) : $payload;
        $body = \is_string($data) ? $data : json_encode($data, \JSON_THROW_ON_ERROR);
        $path = $this->path;
        if ('/v1/chat/completions' === $path && isset(self::MODEL_PATHS[$model->getName()])) {
            $path = self::MODEL_PATHS[$model->getName()];
        }
        $url = $this->baseUrl.$path;

        if (null !== $this->apiKey) {
            return new RawHttpResult($this->httpClient->request('POST', $url, [
                'auth_bearer' => $this->apiKey,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body,
            ]));
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => $this->requestSigner?->sign($url, $path, $body),
            'body' => $body,
        ]));
    }
}
