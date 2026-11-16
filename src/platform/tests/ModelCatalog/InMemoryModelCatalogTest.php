<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\ModelCatalog;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\InMemoryModelCatalog;

final class InMemoryModelCatalogTest extends TestCase
{
    public function testGetModelReturnsConfiguredModel()
    {
        $catalog = new InMemoryModelCatalog([
            'qwen3-coder-next' => [
                'class' => Model::class,
                'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT],
            ],
        ]);

        $model = $catalog->getModel('qwen3-coder-next');

        $this->assertSame('qwen3-coder-next', $model->getName());
        $this->assertSame([Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT], $model->getCapabilities());
    }

    public function testGetModelsReturnsConfiguredModels()
    {
        $models = [
            'qwen3-coder-next' => [
                'class' => Model::class,
                'capabilities' => [Capability::INPUT_MESSAGES],
            ],
        ];

        $this->assertSame($models, (new InMemoryModelCatalog($models))->getModels());
    }

    public function testGetModelThrowsForUnknownModel()
    {
        $this->expectException(ModelNotFoundException::class);

        (new InMemoryModelCatalog())->getModel('unknown');
    }
}
