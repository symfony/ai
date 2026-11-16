<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests;

use Symfony\AI\Platform\Bridge\HuggingFace\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelCatalog;
use Symfony\AI\Platform\Bridge\HuggingFace\Reranker;
use Symfony\AI\Platform\Bridge\HuggingFace\SpeechRecognition;
use Symfony\AI\Platform\Bridge\HuggingFace\Summarization;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'openai/gpt-oss-120b' => ['openai/gpt-oss-120b', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::OUTPUT_STRUCTURED]];
        yield 'openai/gpt-oss-120b with provider' => ['openai/gpt-oss-120b:cerebras', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::OUTPUT_STRUCTURED]];
        yield 'sentence-transformers/all-MiniLM-L6-v2' => ['sentence-transformers/all-MiniLM-L6-v2', Embeddings::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
        yield 'BAAI/bge-small-en-v1.5' => ['BAAI/bge-small-en-v1.5', Embeddings::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
        yield 'cross-encoder/ms-marco-MiniLM-L6-v2' => ['cross-encoder/ms-marco-MiniLM-L6-v2', Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]];
        yield 'google/vit-base-patch16-224' => ['google/vit-base-patch16-224', Classification::class, [Capability::INPUT_IMAGE]];
        yield 'facebook/bart-large-cnn' => ['facebook/bart-large-cnn', Summarization::class, [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]];
        yield 'openai/whisper-large-v3' => ['openai/whisper-large-v3', SpeechRecognition::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
