<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\ModelCatalog;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'nova-micro' => ['nova-micro', Nova::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'nova-lite' => ['nova-lite', Nova::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'nova-pro' => ['nova-pro', Nova::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'nova-2-lite' => ['nova-2-lite', Nova::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'claude-3-haiku-20240307' => ['claude-3-haiku-20240307', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'claude-3-sonnet-20240229' => ['claude-3-sonnet-20240229', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'claude-3-7-sonnet-20250219' => ['claude-3-7-sonnet-20250219', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'claude-sonnet-4-20250514' => ['claude-sonnet-4-20250514', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'claude-haiku-4-5-20251001' => ['claude-haiku-4-5-20251001', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'claude-sonnet-4-5-20250929' => ['claude-sonnet-4-5-20250929', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'claude-opus-4-5-20251101' => ['claude-opus-4-5-20251101', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'claude-sonnet-4-6' => ['claude-sonnet-4-6', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'claude-opus-4-6' => ['claude-opus-4-6', Claude::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'llama-3.2-1b-instruct' => ['llama-3.2-1b-instruct', Llama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'llama-3.2-3b-instruct' => ['llama-3.2-3b-instruct', Llama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
