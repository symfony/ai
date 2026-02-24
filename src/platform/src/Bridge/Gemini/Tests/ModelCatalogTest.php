<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests;

use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\ModelCatalog;
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
        yield 'gemini-3.1-pro-preview' => ['gemini-3.1-pro-preview', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::INPUT_VIDEO, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::OUTPUT_TEXT, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gemini-3-flash-preview' => ['gemini-3-flash-preview', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-3-pro-preview' => ['gemini-3-pro-preview', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::INPUT_VIDEO, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gemini-3-pro-image-preview' => ['gemini-3-pro-image-preview', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::THINKING]];
        yield 'gemini-2.5-flash-image' => ['gemini-2.5-flash-image', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'gemini-2.5-flash' => ['gemini-2.5-flash', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::INPUT_VIDEO, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gemini-2.5-pro' => ['gemini-2.5-pro', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::INPUT_VIDEO, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gemini-2.5-flash-lite-preview-09-2025' => ['gemini-2.5-flash-lite-preview-09-2025', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::INPUT_VIDEO, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gemini-2.5-flash-lite' => ['gemini-2.5-flash-lite', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::INPUT_VIDEO, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gemini-2.0-flash' => ['gemini-2.0-flash', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_VIDEO, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.5-flash-native-audio-preview-12-2025' => ['gemini-2.5-flash-native-audio-preview-12-2025', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_VIDEO, Capability::INPUT_AUDIO, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'gemini-2.5-flash-preview-tts' => ['gemini-2.5-flash-preview-tts', Gemini::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'gemini-2.5-pro-preview-tts' => ['gemini-2.5-pro-preview-tts', Gemini::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'gemini-embedding-001' => ['gemini-embedding-001', Embeddings::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
