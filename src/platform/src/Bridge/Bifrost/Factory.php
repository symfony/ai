<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost;

use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechModelClient;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechResultConverter;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionModelClient;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionResultConverter;
use Symfony\AI\Platform\Bridge\Bifrost\Contract\BifrostContract;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageModelClient;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageResultConverter;
use Symfony\AI\Platform\Bridge\Generic;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] ?string $apiKey = null,
        ?string $endpoint = null,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalogInterface $modelCatalog = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'bifrost',
    ): ProviderInterface {
        if (null === $endpoint && null === $httpClient) {
            throw new InvalidArgumentException('Either an "endpoint" or a pre-configured HTTP client (with a base URI) must be provided to the Bifrost factory.');
        }

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        if (null !== $endpoint) {
            $defaultOptions = [];
            if (null !== $apiKey) {
                $defaultOptions['auth_bearer'] = $apiKey;
            }

            $httpClient = ScopingHttpClient::forBaseUri($httpClient, $endpoint, $defaultOptions);
        }

        $modelCatalog ??= new ModelApiCatalog($httpClient);

        $modelClients = [
            new Generic\Completions\ModelClient($httpClient, '', null, '/v1/chat/completions'),
            new Generic\Embeddings\ModelClient($httpClient, '', null, '/v1/embeddings'),
            new SpeechModelClient($httpClient),
            new TranscriptionModelClient($httpClient),
            new ImageModelClient($httpClient),
        ];

        $resultConverters = [
            new Generic\Completions\ResultConverter(),
            new Generic\Embeddings\ResultConverter(),
            new SpeechResultConverter(),
            new TranscriptionResultConverter(),
            new ImageResultConverter(),
        ];

        return new Provider(
            $name,
            $modelClients,
            $resultConverters,
            $modelCatalog,
            $contract ?? BifrostContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] ?string $apiKey = null,
        ?string $endpoint = null,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalogInterface $modelCatalog = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'bifrost',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $endpoint, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
