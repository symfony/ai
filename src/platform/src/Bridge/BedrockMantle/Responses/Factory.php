<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\BedrockMantle\Responses;

use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\BedrockMantle\ModelClient;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\OpenResponsesContract;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter;
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
 * Bridge for the AWS Bedrock "Mantle" Responses endpoint, the OpenAI-compatible Responses API
 * that AWS recommends for new applications.
 *
 * It reuses the {@see \Symfony\AI\Platform\Bridge\OpenResponses} wire protocol (contract + result
 * conversion) on top of the Mantle {@see ModelClient}, which authenticates with a Bedrock API key
 * (bearer token, recommended) or, when no API key is given, with AWS SigV4 signing. The base URL
 * is derived from the AWS region: "https://bedrock-mantle.<region>.api.aws".
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
        string $path = '/openai/v1/responses',
        string $name = 'bedrock-mantle-responses',
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
            [new ModelClient($httpClient, \sprintf('https://bedrock-mantle.%s.api.aws', $region), $region, $apiKey, $credentialProvider, $path, ResponsesModel::class)],
            [new ResultConverter()],
            $modelCatalog,
            $contract ?? OpenResponsesContract::create(),
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
        string $path = '/openai/v1/responses',
        string $name = 'bedrock-mantle-responses',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $region, $credentialProvider, $httpClient, $modelCatalog, $contract, $eventDispatcher, $path, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
