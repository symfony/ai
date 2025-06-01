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
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeHandler;
use Symfony\AI\Platform\Bridge\Bedrock\Meta\LlamaModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaHandler;

/**
 * @author Björn Altmann
 */
final readonly class PlatformFactory
{
    public static function create(
        BedrockRuntimeClient $bedrockRuntimeClient = new BedrockRuntimeClient(),
    ): Platform {
        $modelClient[] = new ClaudeHandler($bedrockRuntimeClient);
        $modelClient[] = new NovaHandler($bedrockRuntimeClient);
        $modelClient[] = new LlamaModelClient($bedrockRuntimeClient);

        return new Platform($modelClient);
    }
}
