<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Ovh\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // Completions models
        yield 'qwen3guard-gen-8b' => ['qwen3guard-gen-8b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];
        yield 'qwen3guard-gen-0.6b' => ['qwen3guard-gen-0.6b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];
        yield 'qwen3-coder-30b-a3b-instruct' => ['qwen3-coder-30b-a3b-instruct', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gpt-oss-20b' => ['gpt-oss-20b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'gpt-oss-120b' => ['gpt-oss-120b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen3-32b' => ['qwen3-32b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'mistral-small-3.2-24b-instruct-2506' => ['mistral-small-3.2-24b-instruct-2506', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'qwen2.5-vl-72b-instruct' => ['qwen2.5-vl-72b-instruct', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED]];
        yield 'llama-3.1-8b-instruct' => ['llama-3.1-8b-instruct', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'mistral-7b-instruct-v0.3' => ['mistral-7b-instruct-v0.3', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'meta-llama-3_3-70b-instruct' => ['meta-llama-3_3-70b-instruct', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'mistral-nemo-instruct-2407' => ['mistral-nemo-instruct-2407', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // Embeddings models
        yield 'bge-multilingual-gemma2' => ['bge-multilingual-gemma2', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
        yield 'bge-m3' => ['bge-m3', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
