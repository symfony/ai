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
use Symfony\AI\Platform\ModelCatalog\CachedModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedModelCatalogTest extends TestCase
{
    public function testGetModelHitsInnerCatalogOnlyOnce()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $inner = $this->createMock(ModelCatalogInterface::class);
        $inner->expects($this->once())
            ->method('getModel')
            ->with('gpt-4o')
            ->willReturn($model);

        $catalog = new CachedModelCatalog($inner, new ArrayAdapter());

        $this->assertEquals($model, $catalog->getModel('gpt-4o'));
        $this->assertEquals($model, $catalog->getModel('gpt-4o'));
    }

    public function testGetModelsHitsInnerCatalogOnlyOnce()
    {
        $models = ['gpt-4o' => ['class' => Model::class, 'capabilities' => [Capability::INPUT_MESSAGES]]];

        $inner = $this->createMock(ModelCatalogInterface::class);
        $inner->expects($this->once())
            ->method('getModels')
            ->willReturn($models);

        $catalog = new CachedModelCatalog($inner, new ArrayAdapter());

        $this->assertSame($models, $catalog->getModels());
        $this->assertSame($models, $catalog->getModels());
    }

    public function testModelNotFoundIsNotCached()
    {
        $model = new Model('gpt-4o', [Capability::INPUT_MESSAGES]);

        $callCount = 0;
        $inner = $this->createMock(ModelCatalogInterface::class);
        $inner->expects($this->exactly(2))
            ->method('getModel')
            ->with('gpt-4o')
            ->willReturnCallback(static function () use (&$callCount, $model): Model {
                ++$callCount;
                if (1 === $callCount) {
                    throw new ModelNotFoundException('Model "gpt-4o" not found.');
                }

                return $model;
            });

        $catalog = new CachedModelCatalog($inner, new ArrayAdapter());

        try {
            $catalog->getModel('gpt-4o');
            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
        }

        $this->assertEquals($model, $catalog->getModel('gpt-4o'));
    }

    public function testModelNamesWithReservedCharactersAreCachedSeparately()
    {
        $base = new Model('qwen3', [Capability::INPUT_MESSAGES]);
        $variant = new Model('qwen3', [Capability::INPUT_MESSAGES, Capability::TOOL_CALLING]);

        $inner = $this->createMock(ModelCatalogInterface::class);
        $inner->expects($this->exactly(2))
            ->method('getModel')
            ->willReturnCallback(static fn (string $name): Model => str_contains($name, ':32b') ? $variant : $base);

        $catalog = new CachedModelCatalog($inner, new ArrayAdapter());

        $this->assertEquals($base, $catalog->getModel('qwen3'));
        $this->assertEquals($variant, $catalog->getModel('qwen3:32b'));
        $this->assertEquals($base, $catalog->getModel('qwen3'));
        $this->assertEquals($variant, $catalog->getModel('qwen3:32b'));
    }

    public function testCatalogsOfDifferentClassesDoNotShareCacheEntries()
    {
        $cache = new ArrayAdapter();
        $first = new CachedModelCatalog($this->createCatalog('first'), $cache);
        $second = new CachedModelCatalog(new class implements ModelCatalogInterface {
            public function getModel(string $modelName): Model
            {
                return new Model('second');
            }

            public function getModels(): array
            {
                return ['second' => ['class' => Model::class, 'capabilities' => []]];
            }
        }, $cache);

        $this->assertSame('first', $first->getModel('llama3')->getName());
        $this->assertSame('second', $second->getModel('llama3')->getName());
        $this->assertArrayHasKey('first', $first->getModels());
        $this->assertArrayHasKey('second', $second->getModels());
    }

    public function testCatalogsOfSameClassAreSeparatedByNamespace()
    {
        $cache = new ArrayAdapter();
        $first = new CachedModelCatalog($this->createCatalog('first'), $cache, namespace: 'first');
        $second = new CachedModelCatalog($this->createCatalog('second'), $cache, namespace: 'second');

        $this->assertSame('first', $first->getModel('llama3')->getName());
        $this->assertSame('second', $second->getModel('llama3')->getName());
        $this->assertArrayHasKey('first', $first->getModels());
        $this->assertArrayHasKey('second', $second->getModels());
    }

    private function createCatalog(string $name): ModelCatalogInterface
    {
        return new class($name) implements ModelCatalogInterface {
            public function __construct(
                private readonly string $name,
            ) {
            }

            public function getModel(string $modelName): Model
            {
                return new Model($this->name);
            }

            public function getModels(): array
            {
                return [$this->name => ['class' => Model::class, 'capabilities' => []]];
            }
        };
    }
}
