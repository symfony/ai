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

use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\Generic\Completions\ResultConverter;
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
 * Bridge for the AWS Bedrock "Mantle" endpoint, which exposes OpenAI-compatible APIs.
 *
 * Unlike the SigV4/SDK-based {@see \Symfony\AI\Platform\Bridge\Bedrock\Factory}, the Mantle
 * endpoint speaks the plain OpenAI wire protocol. It can be authenticated with a Bedrock API key
 * sent as a bearer token (recommended) or, when no API key is given, with AWS SigV4 signing using
 * the standard credential chain. The base URL is derived from the AWS region:
 * "https://bedrock-mantle.<region>.api.aws".
 *
 * @see https://docs.aws.amazon.com/bedrock/latest/userguide/bedrock-mantle.html
 *
 * @author asrar <aszenz@gmail.com>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $region = 'us-west-2',
        ?CredentialProvider $credentialProvider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'bedrock-mantle',
    ): ProviderInterface {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The Bedrock API key must not be empty.');
        }

        if ('' === $region) {
            throw new InvalidArgumentException('The region must not be empty.');
        }

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new Provider(
            $name,
            [new ModelClient($httpClient, \sprintf('https://bedrock-mantle.%s.api.aws', $region), $region, $apiKey, $credentialProvider)],
            [new ResultConverter()],
            $modelCatalog,
            $contract,
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $region = 'us-west-2',
        ?CredentialProvider $credentialProvider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'bedrock-mantle',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $region, $credentialProvider, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
