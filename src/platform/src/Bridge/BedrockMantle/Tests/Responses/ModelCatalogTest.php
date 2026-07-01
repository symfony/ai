<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\BedrockMantle\Tests\Responses;

use Symfony\AI\Platform\Bridge\BedrockMantle\Responses\ModelCatalog;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
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
        $capabilities = [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::INPUT_MULTIMODAL, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::THINKING];

        yield 'google.gemma-4-31b' => ['google.gemma-4-31b', ResponsesModel::class, $capabilities];
        yield 'google.gemma-4-26b-a4b' => ['google.gemma-4-26b-a4b', ResponsesModel::class, $capabilities];
        yield 'google.gemma-4-e2b' => ['google.gemma-4-e2b', ResponsesModel::class, $capabilities];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
