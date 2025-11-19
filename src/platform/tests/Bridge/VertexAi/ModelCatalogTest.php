<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi;

use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model as EmbeddingsModel;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model as GeminiModel;
use Symfony\AI\Platform\Bridge\VertexAi\ModelCatalog;
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
        // Gemini models
        yield 'gemini-2.5-pro' => ['gemini-2.5-pro', GeminiModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.5-flash' => ['gemini-2.5-flash', GeminiModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.0-flash' => ['gemini-2.0-flash', GeminiModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gemini-2.5-flash-lite' => ['gemini-2.5-flash-lite', GeminiModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemini-2.0-flash-lite' => ['gemini-2.0-flash-lite', GeminiModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_AUDIO, Capability::INPUT_PDF, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // Embeddings models
        yield 'gemini-embedding-001' => ['gemini-embedding-001', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::INPUT_MULTIPLE]];
        yield 'text-embedding-005' => ['text-embedding-005', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::INPUT_MULTIPLE]];
        yield 'text-multilingual-embedding-002' => ['text-multilingual-embedding-002', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::INPUT_MULTIPLE]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
