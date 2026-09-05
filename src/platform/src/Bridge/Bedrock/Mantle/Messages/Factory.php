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

use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AnthropicContract;
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bridge for the Anthropic-compatible Messages API on the AWS Bedrock Mantle endpoint.
 *
 * @see https://docs.aws.amazon.com/bedrock/latest/userguide/inference-messages-api.html
 *
 * @author asrar <aszenz@gmail.com>
 */
final class Factory
{
    /**
     * @param 'none'|'short'|'long' $cacheRetention
     * @param non-empty-string      $name
     */
    public static function createProvider(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $region = 'us-west-2',
        ?CredentialProvider $credentialProvider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $cacheRetention = 'short',
        ?string $workspace = null,
        string $name = 'bedrock-mantle-messages',
    ): ProviderInterface {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The Bedrock API key must not be empty.');
        }

        if ('' === $region) {
            throw new InvalidArgumentException('The region must not be empty.');
        }

        if ('' === $workspace) {
            throw new InvalidArgumentException('The Bedrock Mantle workspace must not be empty.');
        }

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [new ModelClient($httpClient, \sprintf('https://bedrock-mantle.%s.api.aws', $region), $region, $apiKey, $credentialProvider, $cacheRetention, $workspace)],
            [new ResultConverter()],
            $modelCatalog,
            $contract ?? AnthropicContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param 'none'|'short'|'long' $cacheRetention
     * @param non-empty-string      $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $region = 'us-west-2',
        ?CredentialProvider $credentialProvider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $cacheRetention = 'short',
        ?string $workspace = null,
        string $name = 'bedrock-mantle-messages',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $region, $credentialProvider, $httpClient, $modelCatalog, $contract, $eventDispatcher, $cacheRetention, $workspace, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
