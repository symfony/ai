<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\BedrockMantle\Tests;

use Symfony\AI\Platform\Bridge\BedrockMantle\ModelCatalog;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author asrar <aszenz@gmail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        $capabilities = [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING];

        yield 'openai.gpt-oss-120b' => ['openai.gpt-oss-120b', CompletionsModel::class, $capabilities];
        yield 'openai.gpt-oss-20b' => ['openai.gpt-oss-20b', CompletionsModel::class, $capabilities];
        yield 'qwen.qwen3-235b-a22b-2507' => ['qwen.qwen3-235b-a22b-2507', CompletionsModel::class, $capabilities];
        yield 'qwen.qwen3-next-80b-a3b-instruct' => ['qwen.qwen3-next-80b-a3b-instruct', CompletionsModel::class, $capabilities];
        yield 'qwen.qwen3-32b' => ['qwen.qwen3-32b', CompletionsModel::class, $capabilities];
        yield 'qwen.qwen3-coder-30b-a3b-instruct' => ['qwen.qwen3-coder-30b-a3b-instruct', CompletionsModel::class, $capabilities];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
