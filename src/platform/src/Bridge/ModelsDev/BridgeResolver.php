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

use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Bedrock\PlatformFactory as BedrockPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory as VertexAiPlatformFactory;

/**
 * Resolves which Symfony AI bridge to use based on the provider's NPM package.
 *
 * This allows the models.dev bridge to route to specialized bridges for providers
 * that don't use the OpenAI-compatible API format.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class BridgeResolver
{
    /**
     * Mapping of NPM packages to their corresponding Symfony bridge classes.
     *
     * @var array<string, array{factory: class-string, package: string, routable: bool, completionsModelClass?: class-string, embeddingsModelClass?: class-string}>
     */
    private const BRIDGE_MAPPING = [
        '@ai-sdk/anthropic' => [
            'factory' => AnthropicPlatformFactory::class,
            'package' => 'symfony/ai-anthropic-platform',
            'routable' => true, // Compatible factory signature
            'completionsModelClass' => 'Symfony\AI\Platform\Bridge\Anthropic\Claude',
        ],
        '@ai-sdk/google' => [
            'factory' => GeminiPlatformFactory::class,
            'package' => 'symfony/ai-gemini-platform',
            'routable' => true, // Compatible factory signature
            'completionsModelClass' => 'Symfony\AI\Platform\Bridge\Gemini\Gemini',
            'embeddingsModelClass' => 'Symfony\AI\Platform\Bridge\Gemini\Embeddings',
        ],
        '@ai-sdk/google-vertex' => [
            'factory' => VertexAiPlatformFactory::class,
            'package' => 'symfony/ai-vertex-ai-platform',
            'routable' => false, // Requires location and projectId
        ],
        '@ai-sdk/google-vertex/anthropic' => [
            'factory' => VertexAiPlatformFactory::class,
            'package' => 'symfony/ai-vertex-ai-platform',
            'routable' => false, // Requires location and projectId
        ],
        '@ai-sdk/amazon-bedrock' => [
            'factory' => BedrockPlatformFactory::class,
            'package' => 'symfony/ai-bedrock-platform',
            'routable' => false, // Requires BedrockRuntimeClient
        ],
    ];

    /**
     * Check if a provider requires a specialized bridge.
     */
    public static function requiresSpecializedBridge(string $npmPackage): bool
    {
        return isset(self::BRIDGE_MAPPING[$npmPackage]);
    }

    /**
     * Get the bridge factory class name for a given NPM package.
     *
     * @return class-string|null
     */
    public static function getBridgeFactory(string $npmPackage): ?string
    {
        return self::BRIDGE_MAPPING[$npmPackage]['factory'] ?? null;
    }

    /**
     * Get the Composer package name for a given NPM package.
     */
    public static function getComposerPackage(string $npmPackage): ?string
    {
        return self::BRIDGE_MAPPING[$npmPackage]['package'] ?? null;
    }

    /**
     * Check if the required bridge is available (installed).
     */
    public static function isBridgeAvailable(string $npmPackage): bool
    {
        $factory = self::getBridgeFactory($npmPackage);

        return null !== $factory && class_exists($factory);
    }

    /**
     * Check if a bridge can be automatically routed to (has compatible factory signature).
     */
    public static function isRoutable(string $npmPackage): bool
    {
        return self::BRIDGE_MAPPING[$npmPackage]['routable'] ?? false;
    }

    /**
     * Get the completions model class for a given NPM package.
     *
     * @return class-string|null
     */
    public static function getCompletionsModelClass(string $npmPackage): ?string
    {
        return self::BRIDGE_MAPPING[$npmPackage]['completionsModelClass'] ?? null;
    }

    /**
     * Get the embeddings model class for a given NPM package.
     *
     * @return class-string|null
     */
    public static function getEmbeddingsModelClass(string $npmPackage): ?string
    {
        return self::BRIDGE_MAPPING[$npmPackage]['embeddingsModelClass'] ?? null;
    }
}
