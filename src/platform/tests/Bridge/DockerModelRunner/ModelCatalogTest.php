<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\DockerModelRunner;

use Symfony\AI\Platform\Bridge\DockerModelRunner\Completions;
use Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings;
use Symfony\AI\Platform\Bridge\DockerModelRunner\ModelCatalog;
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
        // Completions models
        yield 'ai/gemma3n' => ['ai/gemma3n', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/gemma3' => ['ai/gemma3', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/qwen2.5' => ['ai/qwen2.5', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/qwen3' => ['ai/qwen3', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/qwen3-coder' => ['ai/qwen3-coder', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/llama3.1' => ['ai/llama3.1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/llama3.2' => ['ai/llama3.2', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/llama3.3' => ['ai/llama3.3', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/mistral' => ['ai/mistral', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/mistral-nemo' => ['ai/mistral-nemo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/phi4' => ['ai/phi4', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/deepseek-r1-distill-llama' => ['ai/deepseek-r1-distill-llama', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/seed-oss' => ['ai/seed-oss', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/gpt-oss' => ['ai/gpt-oss', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/smollm2' => ['ai/smollm2', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'ai/smollm3' => ['ai/smollm3', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];

        // Embeddings models
        yield 'ai/nomic-embed-text-v1.5' => ['ai/nomic-embed-text-v1.5', Embeddings::class, [Capability::INPUT_TEXT]];
        yield 'ai/mxbai-embed-large' => ['ai/mxbai-embed-large', Embeddings::class, [Capability::INPUT_TEXT]];
        yield 'ai/embeddinggemma' => ['ai/embeddinggemma', Embeddings::class, [Capability::INPUT_TEXT]];
        yield 'ai/granite-embedding-multilingual' => ['ai/granite-embedding-multilingual', Embeddings::class, [Capability::INPUT_TEXT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
