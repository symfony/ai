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
use Symfony\AI\Platform\ModelCatalog\CompositeModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;

final class CompositeModelCatalogTest extends TestCase
{
    public function testGetModelReturnsFirstMatch()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $catalog1 = $this->createStub(ModelCatalogInterface::class);
        $catalog1->method('getModel')->willThrowException(new ModelNotFoundException('Not found'));

        $catalog2 = $this->createStub(ModelCatalogInterface::class);
        $catalog2->method('getModel')->willReturn($model);

        $composite = new CompositeModelCatalog([$catalog1, $catalog2]);

        $this->assertSame($model, $composite->getModel('gpt-4o'));
    }

    public function testGetModelPrefersFirstCatalog()
    {
        $model1 = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);
        $model2 = new Model('gpt-4o', [Capability::INPUT_MESSAGES, Capability::OUTPUT_STREAMING]);

        $catalog1 = $this->createStub(ModelCatalogInterface::class);
        $catalog1->method('getModel')->willReturn($model1);

        $catalog2 = $this->createStub(ModelCatalogInterface::class);
        $catalog2->method('getModel')->willReturn($model2);

        $composite = new CompositeModelCatalog([$catalog1, $catalog2]);

        $this->assertSame($model1, $composite->getModel('gpt-4o'));
    }

    public function testGetModelThrowsWhenNotFoundInAnyCatalog()
    {
        $catalog1 = $this->createStub(ModelCatalogInterface::class);
        $catalog1->method('getModel')->willThrowException(new ModelNotFoundException('Not found'));

        $catalog2 = $this->createStub(ModelCatalogInterface::class);
        $catalog2->method('getModel')->willThrowException(new ModelNotFoundException('Not found'));

        $composite = new CompositeModelCatalog([$catalog1, $catalog2]);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found in any registered catalog/');

        $composite->getModel('nonexistent');
    }

    public function testGetModelsMergesAllCatalogs()
    {
        $catalog1 = $this->createStub(ModelCatalogInterface::class);
        $catalog1->method('getModels')->willReturn([
            'gpt-4o' => ['class' => Model::class, 'capabilities' => [Capability::INPUT_MESSAGES]],
        ]);

        $catalog2 = $this->createStub(ModelCatalogInterface::class);
        $catalog2->method('getModels')->willReturn([
            'claude-3-5-sonnet' => ['class' => Model::class, 'capabilities' => [Capability::INPUT_MESSAGES]],
        ]);

        $composite = new CompositeModelCatalog([$catalog1, $catalog2]);

        $models = $composite->getModels();

        $this->assertCount(2, $models);
        $this->assertArrayHasKey('gpt-4o', $models);
        $this->assertArrayHasKey('claude-3-5-sonnet', $models);
    }

    public function testGetModelsFirstCatalogWinsOnConflict()
    {
        $catalog1 = $this->createStub(ModelCatalogInterface::class);
        $catalog1->method('getModels')->willReturn([
            'gpt-4o' => ['class' => Model::class, 'capabilities' => [Capability::INPUT_MESSAGES]],
        ]);

        $catalog2 = $this->createStub(ModelCatalogInterface::class);
        $catalog2->method('getModels')->willReturn([
            'gpt-4o' => ['class' => Model::class, 'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_STREAMING]],
        ]);

        $composite = new CompositeModelCatalog([$catalog1, $catalog2]);

        $models = $composite->getModels();

        $this->assertCount(1, $models);
        $this->assertNotContains(Capability::OUTPUT_STREAMING, $models['gpt-4o']['capabilities']);
    }

    public function testEmptyCatalogs()
    {
        $composite = new CompositeModelCatalog([]);

        $this->expectException(ModelNotFoundException::class);

        $composite->getModel('anything');
    }

    public function testGetModelsWithEmptyCatalogs()
    {
        $composite = new CompositeModelCatalog([]);

        $this->assertSame([], $composite->getModels());
    }
}
