<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\DynamicModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;

/**
 * Base test case for testing ModelCatalog implementations.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class ModelCatalogTestCase extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string<Model>, list<Capability>}>
     */
    abstract public static function modelsProvider(): iterable;

    /**
     * @param class-string<Model> $expectedClass
     * @param list<Capability>    $expectedCapabilities
     */
    #[DataProvider('modelsProvider')]
    public function testGetModel(string $modelName, string $expectedClass, array $expectedCapabilities)
    {
        $catalog = $this->createModelCatalog();
        $model = $catalog->getModel($modelName);

        $this->assertInstanceOf(Model::class, $model);
        $this->assertInstanceOf($expectedClass, $model);
        $this->assertSame($modelName, $model->getName());

        // Check capabilities
        $actualCapabilities = $model->getCapabilities();
        sort($expectedCapabilities);
        sort($actualCapabilities);

        $this->assertSame(
            $expectedCapabilities,
            $actualCapabilities,
            \sprintf('Model "%s" capabilities do not match expected', $modelName)
        );
    }

    public function testGetModelThrowsExceptionForUnknownModel()
    {
        $catalog = $this->createModelCatalog();

        // Skip this test for catalogs that accept any model (like DynamicModelCatalog)
        if ($catalog instanceof DynamicModelCatalog) {
            $this->markTestSkipped('This catalog accepts any model name');
        }

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "unknown-model-that-does-not-exist" not found');

        $catalog->getModel('unknown-model-that-does-not-exist');
    }

    public function testGetModels()
    {
        $catalog = $this->createModelCatalog();
        $models = $catalog->getModels();

        // Skip this test for catalogs that accept any model (like DynamicModelCatalog)
        if ($catalog instanceof DynamicModelCatalog) {
            $this->markTestSkipped('This catalog accepts any model name');
        }

        foreach ($models as $modelName => $modelDefinition) {
            $this->assertIsString($modelName);
            $this->assertArrayHasKey('class', $modelDefinition);
            $this->assertArrayHasKey('capabilities', $modelDefinition);
            $this->assertIsArray($modelDefinition['capabilities']);

            // Verify each capability is valid
            foreach ($modelDefinition['capabilities'] as $capability) {
                $this->assertInstanceOf(Capability::class, $capability);
            }
        }
    }

    public function testAllModelsHaveValidClass()
    {
        $catalog = $this->createModelCatalog();

        // Skip this test for catalogs that accept any model (like DynamicModelCatalog)
        if ($catalog instanceof DynamicModelCatalog) {
            $this->markTestSkipped('This catalog accepts any model name');
        }

        $models = $catalog->getModels();

        foreach ($models as $modelName => $modelDefinition) {
            $this->assertArrayHasKey('class', $modelDefinition, \sprintf('Model "%s" missing class', $modelName));
            $this->assertTrue(
                class_exists($modelDefinition['class']),
                \sprintf('Model "%s" has non-existent class "%s"', $modelName, $modelDefinition['class'])
            );
            $this->assertTrue(
                is_subclass_of($modelDefinition['class'], Model::class) || Model::class === $modelDefinition['class'],
                \sprintf('Model "%s" class "%s" must extend Model', $modelName, $modelDefinition['class'])
            );
        }
    }

    abstract protected function createModelCatalog(): ModelCatalogInterface;
}
