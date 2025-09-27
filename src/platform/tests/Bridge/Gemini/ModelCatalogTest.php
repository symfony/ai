<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\ModelCatalog;
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
        yield 'gemini-2.5-flash' => ['gemini-2.5-flash', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.5-pro' => ['gemini-2.5-pro', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.5-flash-lite' => ['gemini-2.5-flash-lite', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.0-flash' => ['gemini-2.0-flash', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.0-pro-exp-02-05' => ['gemini-2.0-pro-exp-02-05', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.0-flash-lite-preview-02-05' => ['gemini-2.0-flash-lite-preview-02-05', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.0-flash-thinking-exp-01-21' => ['gemini-2.0-flash-thinking-exp-01-21', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-1.5-flash' => ['gemini-1.5-flash', Gemini::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-embedding-exp-03-07' => ['gemini-embedding-exp-03-07', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'text-embedding-004' => ['text-embedding-004', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'embedding-001' => ['embedding-001', Embeddings::class, [Capability::INPUT_MULTIPLE]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
