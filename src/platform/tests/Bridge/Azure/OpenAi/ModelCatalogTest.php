<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Azure\OpenAi;

use Symfony\AI\Platform\Bridge\Azure\OpenAi\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Tests\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // Azure OpenAI uses deployment names, so we test with example deployment names
        // Since it extends DynamicModelCatalog, all capabilities are provided
        yield 'my-gpt4-deployment' => ['my-gpt4-deployment', Model::class, Capability::cases()];
        yield 'custom-embedding-deployment' => ['custom-embedding-deployment', Model::class, Capability::cases()];
        yield 'test-model-deployment' => ['test-model-deployment', Model::class, Capability::cases()];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
