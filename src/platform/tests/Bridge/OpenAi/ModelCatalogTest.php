<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\DallE;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Tests\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // GPT models
        yield 'gpt-3.5-turbo' => ['gpt-3.5-turbo', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-3.5-turbo-instruct' => ['gpt-3.5-turbo-instruct', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-4' => ['gpt-4', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-4-turbo' => ['gpt-4-turbo', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'gpt-4o' => ['gpt-4o', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-mini' => ['gpt-4o-mini', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-audio-preview' => ['gpt-4o-audio-preview', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_AUDIO, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'o1-mini' => ['o1-mini', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'o1-preview' => ['o1-preview', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'o3-mini' => ['o3-mini', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'o3-mini-high' => ['o3-mini-high', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-4.5-preview' => ['gpt-4.5-preview', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4.1' => ['gpt-4.1', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4.1-mini' => ['gpt-4.1-mini', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4.1-nano' => ['gpt-4.1-nano', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-5' => ['gpt-5', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-5-chat-latest' => ['gpt-5-chat-latest', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::INPUT_IMAGE]];
        yield 'gpt-5-mini' => ['gpt-5-mini', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-5-nano' => ['gpt-5-nano', Gpt::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];

        // Embedding models
        yield 'text-embedding-ada-002' => ['text-embedding-ada-002', Embeddings::class, [Capability::INPUT_TEXT]];
        yield 'text-embedding-3-large' => ['text-embedding-3-large', Embeddings::class, [Capability::INPUT_TEXT]];
        yield 'text-embedding-3-small' => ['text-embedding-3-small', Embeddings::class, [Capability::INPUT_TEXT]];

        // Whisper models
        yield 'whisper-1' => ['whisper-1', Whisper::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT]];

        // DALL-E models
        yield 'dall-e-2' => ['dall-e-2', DallE::class, [Capability::INPUT_TEXT, Capability::OUTPUT_IMAGE]];
        yield 'dall-e-3' => ['dall-e-3', DallE::class, [Capability::INPUT_TEXT, Capability::OUTPUT_IMAGE]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
