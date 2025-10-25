<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Factory;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ProviderFactory implements ProviderFactoryInterface
{
    public function __construct(private ?HttpClientInterface $http = null)
    {
    }

    public function fromDsn(string $dsn): object
    {
        $config = ProviderConfigFactory::fromDsn($dsn);
        $providerKey = strtolower($config->provider);

        if ('azure' === $providerKey) {
            $engine = strtolower($config->options['engine'] ?? 'openai');
            $factoryFqcn = match ($engine) {
                'openai' => \Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory::class,
                'meta' => \Symfony\AI\Platform\Bridge\Azure\Meta\PlatformFactory::class,
                default => throw new InvalidArgumentException(\sprintf('Unsupported Azure engine "%s". Supported: "openai", "meta".', $engine)),
            };
        } else {
            $factoryMap = [
                'openai' => \Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory::class,
                'anthropic' => \Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory::class,
                'azure' => \Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory::class,
                'gemini' => \Symfony\AI\Platform\Bridge\Gemini\PlatformFactory::class,
                'vertex' => \Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory::class,
                'ollama' => \Symfony\AI\Platform\Bridge\Ollama\PlatformFactory::class,
            ];

            if (!isset($factoryMap[$providerKey])) {
                throw new InvalidArgumentException(\sprintf('Unsupported AI provider "%s".', $config->provider));
            }

            $factoryFqcn = $factoryMap[$providerKey];
        }

        $authHeaders = match ($providerKey) {
            'openai', 'anthropic', 'gemini', 'vertex' => $config->apiKey ? ['Authorization' => 'Bearer '.$config->apiKey] : [],
            'azure' => $config->apiKey ? ['api-key' => $config->apiKey] : [],
            default => [],
        };

        $headers = array_filter($authHeaders + $config->headers, static fn ($v) => null !== $v && '' !== $v);

        $http = $this->http ?? HttpClient::create([
            'base_uri' => $config->baseUri,
            'headers' => $headers,
            'timeout' => isset($config->options['timeout']) ? (float) $config->options['timeout'] : null,
            'proxy' => $config->options['proxy'] ?? null,
            'verify_peer' => $config->options['verify_peer'] ?? null,
        ]);

        $contract = [
            'provider' => $config->provider,
            'base_uri' => $config->baseUri,
            'options' => $config->options,
            'headers' => $headers,
        ];

        return $factoryFqcn::create($config->apiKey ?? '', $http, $contract);
    }
}
