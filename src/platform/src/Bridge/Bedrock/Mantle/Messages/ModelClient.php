<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages;

use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\PromptCachingTrait;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\SigV4RequestSigner;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author asrar <aszenz@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;
    use PromptCachingTrait;

    private const PATH = '/anthropic/v1/messages';

    private readonly EventSourceHttpClient $httpClient;
    private readonly ?SigV4RequestSigner $requestSigner;

    /**
     * @param 'none'|'short'|'long' $cacheRetention
     */
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        string $region,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        ?CredentialProvider $credentialProvider = null,
        private readonly string $cacheRetention = 'short',
        private readonly ?string $workspace = null,
    ) {
        if (!\in_array($cacheRetention, ['none', 'short', 'long'], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid cache retention "%s". Supported values are "none", "short" and "long".', $cacheRetention));
        }

        if (null === $apiKey) {
            $this->requestSigner = new SigV4RequestSigner($region, $credentialProvider ?? ChainProvider::createDefaultChain($httpClient));
        } else {
            $this->requestSigner = null;
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        if (isset($options['response_format']) || isset($options['output_config']['format']) || isset($payload['response_format']) || isset($payload['output_config']['format'])) {
            throw new InvalidArgumentException('Structured outputs are not supported by the Anthropic Messages API on the Bedrock Mantle endpoint.');
        }

        $cacheControl = $this->getCacheControl($this->cacheRetention);
        $payload = $this->injectMessagesCacheControl($payload, $cacheControl);
        $payload = $this->injectSystemCacheControl($payload, $cacheControl);

        if (isset($options['tools'])) {
            $options['tool_choice'] ??= ['type' => 'auto'];
            $options['tools'] = $this->injectToolsCacheControl($options['tools'], $cacheControl);
        }

        if ('enabled' === ($options['thinking']['type'] ?? null)) {
            $options['beta_features'][] = 'interleaved-thinking-2025-05-14';
        }

        $headers = [
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        if (isset($options['beta_features']) && \is_array($options['beta_features']) && \count($options['beta_features']) > 0) {
            $headers['anthropic-beta'] = implode(',', $options['beta_features']);
            unset($options['beta_features']);
        }

        if (null !== $this->workspace) {
            $headers['anthropic-workspace'] = $this->workspace;
        }

        $body = $this->encodeJsonBody(array_merge($options, ['model' => $model->getName()], $payload));
        $url = $this->baseUrl.self::PATH;

        if (null !== $this->apiKey) {
            $headers['x-api-key'] = $this->apiKey;
        } else {
            $headers = $this->requestSigner?->sign($url, self::PATH, $body, $headers) ?? $headers;
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $body,
        ]));
    }
}
