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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog;
use Symfony\AI\Platform\InMemoryPlatform;

class ModelCatalogTest extends TestCase
{
    public function testGetModels(): void
    {
        $configPath = __DIR__.'/Fixtures/test_models.yaml';
        $catalog = new ModelCatalog($configPath);

        $models = $catalog->getModels();
        
        self::assertArrayHasKey('test-model', $models);
        self::assertSame('TestModelClass', $models['test-model']['class']);
        self::assertSame(['input-text', 'output-text'], $models['test-model']['capabilities']);
    }

    public function testGetModel(): void
    {
        $configPath = __DIR__.'/Fixtures/test_models.yaml';
        $catalog = new ModelCatalog($configPath);

        $model = $catalog->getModel('test-model');
        
        self::assertNotNull($model);
        self::assertSame('TestModelClass', $model['class']);
        self::assertSame(['input-text', 'output-text'], $model['capabilities']);
        
        $nonExistentModel = $catalog->getModel('non-existent');
        self::assertNull($nonExistentModel);
    }

    public function testGetCapabilities(): void
    {
        $configPath = __DIR__.'/Fixtures/test_models.yaml';
        $catalog = new ModelCatalog($configPath);

        $capabilities = $catalog->getCapabilities('test-model');
        
        self::assertCount(2, $capabilities);
        self::assertContains(Capability::INPUT_TEXT, $capabilities);
        self::assertContains(Capability::OUTPUT_TEXT, $capabilities);
        
        $emptyCapabilities = $catalog->getCapabilities('non-existent');
        self::assertSame([], $emptyCapabilities);
    }

    public function testSupportsWithInMemoryPlatform(): void
    {
        $configPath = __DIR__.'/Fixtures/test_models.yaml';
        $catalog = new ModelCatalog($configPath);
        $platform = new InMemoryPlatform('mock response');

        
        $supports = $catalog->supports($platform);
        self::assertIsBool($supports);
    }

    public function testConstructorThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model configuration file "/non/existent/path.yaml" does not exist.');
        
        new ModelCatalog('/non/existent/path.yaml');
    }
}