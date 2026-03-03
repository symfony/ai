<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Generic\PlatformFactory as GenericPlatformFactory;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Creates a Platform instance for any models.dev provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PlatformFactory
{
    /**
     * Well-known API base URLs for providers whose models.dev entry
     * omits the "api" field because the Vercel AI SDK hardcodes the
     * URL inside their dedicated npm packages.
     *
     * @var array<string, string> npm package => base URL
     */
    private const NPM_PACKAGE_BASE_URLS = [
        '@ai-sdk/cerebras' => 'https://api.cerebras.ai',
        '@ai-sdk/cohere' => 'https://api.cohere.com/compatibility',
        '@ai-sdk/deepinfra' => 'https://api.deepinfra.com/v1/openai',
        '@ai-sdk/groq' => 'https://api.groq.com/openai',
        '@ai-sdk/mistral' => 'https://api.mistral.ai',
        '@ai-sdk/openai' => 'https://api.openai.com',
        '@ai-sdk/perplexity' => 'https://api.perplexity.ai',
        '@ai-sdk/togetherai' => 'https://api.together.xyz',
        '@ai-sdk/xai' => 'https://api.x.ai',
    ];

    public static function create(
        string $provider,
        #[\SensitiveParameter] ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $dataPath = null,
        ?Contract $contract = null,
        ?HttpClientInterface $httpClient = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $data = DataLoader::load($dataPath);
        if (!isset($data[$provider])) {
            throw new InvalidArgumentException(\sprintf('Provider "%s" not found in models.dev data.', $provider));
        }
        $providerData = $data[$provider];
        $npmPackage = $providerData['npm'] ?? null;

        // Check if this provider requires a specialized bridge (e.g., Anthropic, Google).
        // When a custom baseUrl is given, skip this check: the user is pointing at an
        // OpenAI-compatible proxy, so the generic bridge is always the right choice.
        if (null === $baseUrl && null !== $npmPackage && BridgeResolver::requiresSpecializedBridge($npmPackage)) {
            $package = BridgeResolver::getComposerPackage($npmPackage);
            $factoryClass = BridgeResolver::getBridgeFactory($npmPackage);

            if (!BridgeResolver::isBridgeAvailable($npmPackage)) {
                throw new InvalidArgumentException(\sprintf('Provider "%s" requires a specialized bridge (%s); install it with composer require "%s".', $provider, $npmPackage, $package));
            }
            if (!BridgeResolver::isRoutable($npmPackage)) {
                throw new InvalidArgumentException(\sprintf('Provider "%s" requires "%s" which has a different factory signature; use "%s::create()" directly.', $provider, $package, $factoryClass));
            }

            $modelCatalog = new ModelCatalog(
                $provider,
                $dataPath,
                completionsModelClass: BridgeResolver::getCompletionsModelClass($npmPackage),
                embeddingsModelClass: BridgeResolver::getEmbeddingsModelClass($npmPackage),
            );

            return $factoryClass::create(
                apiKey: $apiKey,
                modelCatalog: $modelCatalog,
                contract: $contract,
                httpClient: $httpClient,
                eventDispatcher: $eventDispatcher,
            );
        }

        // Use the generic OpenAI-compatible bridge
        if (null === $baseUrl) {
            $baseUrl = (new ProviderRegistry($dataPath))->getApiBaseUrl($provider);
        }
        if (null === $baseUrl && null !== $npmPackage) {
            $baseUrl = self::NPM_PACKAGE_BASE_URLS[$npmPackage] ?? null;
        }
        if (null === $baseUrl) {
            throw new InvalidArgumentException(\sprintf('Provider "%s" does not have a known API base URL; please provide one via the $baseUrl argument.', $provider));
        }

        // Base URL should NOT include /v1 suffix (e.g., https://api.groq.com/openai not https://api.groq.com/openai/v1)
        // The paths /v1/chat/completions and /v1/embeddings will be appended by the Generic bridge
        $baseUrl = rtrim($baseUrl, '/');

        // Automatically detect what the provider supports based on its model catalog
        $supportsCompletions = false;
        $supportsEmbeddings = false;
        $modelCatalog = new ModelCatalog($provider, $dataPath);
        foreach ($modelCatalog->getModels() as $modelData) {
            if (CompletionsModel::class === $modelData['class'] || is_subclass_of($modelData['class'], CompletionsModel::class)) {
                $supportsCompletions = true;
            }
            if (EmbeddingsModel::class === $modelData['class'] || is_subclass_of($modelData['class'], EmbeddingsModel::class)) {
                $supportsEmbeddings = true;
            }

            // Early exit if we found both types
            if ($supportsCompletions && $supportsEmbeddings) {
                break;
            }
        }

        return GenericPlatformFactory::create(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            modelCatalog: $modelCatalog,
            contract: $contract,
            httpClient: $httpClient,
            eventDispatcher: $eventDispatcher,
            supportsCompletions: $supportsCompletions,
            supportsEmbeddings: $supportsEmbeddings,
        );
    }
}
