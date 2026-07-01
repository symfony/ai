<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\BedrockMantle;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Credentials\Credentials;
use AsyncAws\Core\Request;
use AsyncAws\Core\RequestContext;
use AsyncAws\Core\Signer\SignerV4;
use AsyncAws\Core\Stream\StringStream;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Exception\RuntimeException;
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
    private const SERVICE = 'bedrock';

    private readonly EventSourceHttpClient $httpClient;

    /**
     * The credential provider used for SigV4 signing. Only resolved when no API key is used.
     */
    private readonly ?CredentialProvider $credentialProvider;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $region,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        ?CredentialProvider $credentialProvider = null,
        private readonly string $path = '/v1/chat/completions',
        private readonly string $supportedModel = CompletionsModel::class,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        if (null === $apiKey) {
            $this->credentialProvider = $credentialProvider ?? ChainProvider::createDefaultChain($this->httpClient);
        } else {
            $this->credentialProvider = null;
        }
    }

    public function supports(Model $model): bool
    {
        return $model instanceof $this->supportedModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $data = \is_array($payload) ? array_merge($options, ['model' => $model->getName()], $payload) : $payload;
        $body = \is_string($data) ? $data : json_encode($data, \JSON_THROW_ON_ERROR);
        $url = $this->baseUrl.$this->path;

        if (null !== $this->apiKey) {
            return new RawHttpResult($this->httpClient->request('POST', $url, [
                'auth_bearer' => $this->apiKey,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body,
            ]));
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => $this->sign($url, $body),
            'body' => $body,
        ]));
    }

    /**
     * Signs the request with AWS SigV4 and returns the resulting headers.
     *
     * @return array<string, string>
     */
    private function sign(string $url, string $body): array
    {
        $credentials = $this->credentialProvider?->getCredentials(Configuration::create(['region' => $this->region]));
        if (!$credentials instanceof Credentials) {
            throw new RuntimeException('Unable to resolve AWS credentials for Bedrock Mantle SigV4 authentication.');
        }

        $request = new Request('POST', $this->path, [], ['content-type' => 'application/json'], StringStream::create($body));
        $request->setEndpoint($url);

        (new SignerV4(self::SERVICE, $this->region))->sign($request, $credentials, new RequestContext());

        return $request->getHeaders();
    }
}
