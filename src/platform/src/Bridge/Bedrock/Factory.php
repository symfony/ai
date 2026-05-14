<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use Symfony\AI\Platform\Bridge\Anthropic\Contract as AnthropicContract;
use Symfony\AI\Platform\Bridge\Anthropic\MessagesClient as AnthropicMessagesClient;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\Transport\BedrockTransport as AnthropicBedrockTransport;
use Symfony\AI\Platform\Bridge\Bedrock\Meta\InvokeClient as MetaInvokeClient;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract as NovaContract;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\InvokeClient as NovaInvokeClient;
use Symfony\AI\Platform\Bridge\Meta\Contract as LlamaContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Björn Altmann
 */
final class Factory
{
    /**
     * @param non-empty-string $name
     */
    public static function createProvider(
        ?BedrockRuntimeClient $bedrockRuntimeClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'bedrock',
    ): ProviderInterface {
        if (!class_exists(BedrockRuntimeClient::class)) {
            throw new RuntimeException('For using the Bedrock platform, the async-aws/bedrock-runtime package is required. Try running "composer require async-aws/bedrock-runtime".');
        }

        if (null === $bedrockRuntimeClient) {
            $bedrockRuntimeClient = new BedrockRuntimeClient();
        }

        // One client per vendor — each pairs the vendor's request/response
        // shape with a transport that knows the vendor's model-id construction.
        // Bedrock defaults to no prompt-caching to preserve historical behavior
        // on this platform — direct Anthropic users opt into 'short'/'long'
        // via the Anthropic factory instead.
        $clients = [
            new AnthropicMessagesClient(new AnthropicBedrockTransport($bedrockRuntimeClient), 'none'),
            new MetaInvokeClient($bedrockRuntimeClient),
            new NovaInvokeClient($bedrockRuntimeClient),
        ];

        return new Provider(
            $name,
            $clients,
            $clients,
            $modelCatalog,
            $contract ?? Contract::create([
                new AnthropicContract\AssistantMessageNormalizer(),
                new AnthropicContract\DocumentNormalizer(),
                new AnthropicContract\DocumentUrlNormalizer(),
                new AnthropicContract\ImageNormalizer(),
                new AnthropicContract\ImageUrlNormalizer(),
                new AnthropicContract\MessageBagNormalizer(),
                new AnthropicContract\ToolCallMessageNormalizer(),
                new AnthropicContract\ToolNormalizer(),
                new LlamaContract\MessageBagNormalizer(),
                new NovaContract\AssistantMessageNormalizer(),
                new NovaContract\MessageBagNormalizer(),
                new NovaContract\ToolCallMessageNormalizer(),
                new NovaContract\ToolNormalizer(),
                new NovaContract\UserMessageNormalizer(),
            ]),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        ?BedrockRuntimeClient $bedrockRuntimeClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'bedrock',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($bedrockRuntimeClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
