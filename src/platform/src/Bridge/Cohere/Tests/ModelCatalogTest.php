<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\ModelCatalog;
use Symfony\AI\Platform\Bridge\Cohere\Reranker;
use Symfony\AI\Platform\Bridge\Cohere\SpeechToText;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // Chat models
        yield 'command-a-03-2025' => ['command-a-03-2025', Cohere::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'command-a-vision-07-2025' => ['command-a-vision-07-2025', Cohere::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'command-a-translate-08-2025' => ['command-a-translate-08-2025', Cohere::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];
        yield 'command-a-reasoning-08-2025' => ['command-a-reasoning-08-2025', Cohere::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'command-r-plus-08-2024' => ['command-r-plus-08-2024', Cohere::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'command-r-08-2024' => ['command-r-08-2024', Cohere::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'command-r7b-12-2024' => ['command-r7b-12-2024', Cohere::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];

        // Aya models
        yield 'c4ai-aya-expanse-32b' => ['c4ai-aya-expanse-32b', Cohere::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];
        yield 'c4ai-aya-vision-32b' => ['c4ai-aya-vision-32b', Cohere::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];

        // Embedding models
        yield 'embed-v4.0' => ['embed-v4.0', Embeddings::class, [Capability::INPUT_MULTIPLE, Capability::INPUT_MULTIMODAL, Capability::EMBEDDINGS]];
        yield 'embed-english-v3.0' => ['embed-english-v3.0', Embeddings::class, [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS]];
        yield 'embed-multilingual-v3.0' => ['embed-multilingual-v3.0', Embeddings::class, [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS]];
        yield 'embed-english-light-v3.0' => ['embed-english-light-v3.0', Embeddings::class, [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS]];
        yield 'embed-multilingual-light-v3.0' => ['embed-multilingual-light-v3.0', Embeddings::class, [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS]];

        // Reranking models
        yield 'rerank-v4.0-pro' => ['rerank-v4.0-pro', Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]];
        yield 'rerank-v4.0-fast' => ['rerank-v4.0-fast', Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]];
        yield 'rerank-v3.5' => ['rerank-v3.5', Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]];
        yield 'rerank-english-v3.0' => ['rerank-english-v3.0', Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]];
        yield 'rerank-multilingual-v3.0' => ['rerank-multilingual-v3.0', Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]];

        // Speech-to-text models
        yield 'cohere-transcribe-03-2026' => ['cohere-transcribe-03-2026', SpeechToText::class, [Capability::INPUT_AUDIO, Capability::SPEECH_TO_TEXT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
