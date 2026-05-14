<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

use Google\Auth\ApplicationDefaultCredentials;
use Symfony\AI\Platform\Bridge\Gemini\GenerateContentClient;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\VertexAi\Transport\VertexAiTransport;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
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
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        ?string $location = null,
        ?string $projectId = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'vertexai',
    ): ProviderInterface {
        if ((null === $location) !== (null === $projectId)) {
            throw new InvalidArgumentException('Both "location" and "projectId" must be provided together for the project-scoped VertexAI endpoint, or both must be null to use the global endpoint.');
        }

        if (null === $location && null === $apiKey) {
            throw new InvalidArgumentException('An API key is required when using the global VertexAI endpoint (no location/projectId).');
        }

        if (null !== $location && !class_exists(ApplicationDefaultCredentials::class)) {
            throw new RuntimeException('For using the project-scoped Vertex AI endpoint, google/auth package is required for authentication via application default credentials. Try running "composer require google/auth".');
        }

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $transport = new VertexAiTransport($httpClient, $location, $projectId, $apiKey);
        $clients = [
            new GenerateContentClient($transport, GenerateContentClient::RESPONSE_SCHEMA_KEY_VERTEX_AI),
            new PredictEmbeddingsClient($transport),
        ];

        return new Provider(
            $name,
            $clients,
            $clients,
            $modelCatalog,
            $contract ?? GeminiContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        ?string $location = null,
        ?string $projectId = null,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'vertexai',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($location, $projectId, $apiKey, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
