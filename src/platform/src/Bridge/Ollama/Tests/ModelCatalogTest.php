<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use Symfony\AI\Platform\Bridge\Ollama\ModelCatalog;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
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
        yield 'deepseek-r1' => ['deepseek-r1', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gpt-oss' => ['gpt-oss', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'llama3.1' => ['llama3.1', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'llama3.2' => ['llama3.2', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'llama3' => ['llama3', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'mistral' => ['mistral', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen3' => ['qwen3', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen3:32b' => ['qwen3:32b', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen' => ['qwen', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen2' => ['qwen2', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen2.5' => ['qwen2.5', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen2.5-coder' => ['qwen2.5-coder', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gemma3n' => ['gemma3n', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'gemma3' => ['gemma3', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'qwen2.5vl' => ['qwen2.5vl', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'llava' => ['llava', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'phi3' => ['phi3', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'gemma2' => ['gemma2', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'gemma' => ['gemma', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'llama2' => ['llama2', Ollama::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STRUCTURED]];
        yield 'nomic-embed-text' => ['nomic-embed-text', Ollama::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS, Capability::INPUT_MULTIPLE]];
        yield 'bge-m3' => ['bge-m3', Ollama::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS, Capability::INPUT_MULTIPLE]];
        yield 'all-minilm' => ['all-minilm', Ollama::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS, Capability::INPUT_MULTIPLE]];
        yield 'all-minilm:33m' => ['all-minilm:33m', Ollama::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS, Capability::INPUT_MULTIPLE]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
