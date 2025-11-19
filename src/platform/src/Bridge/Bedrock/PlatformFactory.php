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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Anthropic\Contract as AnthropicContract;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\Meta\LlamaModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\Meta\LlamaResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract as NovaContract;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaResultConverter;
use Symfony\AI\Platform\Bridge\Meta\Contract as LlamaContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;

/**
 * @author Björn Altmann
 */
final class PlatformFactory
{
    public static function create(
        BedrockRuntimeClient $bedrockRuntimeClient = new BedrockRuntimeClient(),
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): Platform {
        if (!class_exists(BedrockRuntimeClient::class)) {
            throw new RuntimeException('For using the Bedrock platform, the async-aws/bedrock-runtime package is required. Try running "composer require async-aws/bedrock-runtime".');
        }

        return new Platform(
            [
                new ClaudeModelClient($bedrockRuntimeClient),
                new LlamaModelClient($bedrockRuntimeClient),
                new NovaModelClient($bedrockRuntimeClient),
            ],
            [
                new ClaudeResultConverter(),
                new LlamaResultConverter(),
                new NovaResultConverter(),
            ],
            $modelCatalog,
            $contract ?? Contract::create(
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
            ),
            $eventDispatcher,
        );
    }
}
