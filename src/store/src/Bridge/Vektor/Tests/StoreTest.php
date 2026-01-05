<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Vektor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Vektor\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupWithOptions()
    {
        $store = new Store(sys_get_temp_dir());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $store->setup([
            'foo' => 'bar',
        ]);
    }

    public function testStoreCannotDropUndefinedDirectory()
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('exists')->willReturn(false);
        $filesystem->expects($this->never())->method('remove');

        $store = new Store(sys_get_temp_dir(), filesystem: $filesystem);

        $store->drop();
    }

    public function testStoreCanDropExistingDirectory()
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('exists')->willReturn(true);
        $filesystem->expects($this->once())->method('remove');

        $store = new Store(sys_get_temp_dir(), filesystem: $filesystem);

        $store->drop();
    }

    public function testStoreCanOptimize()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $store->drop([
            'optimize' => true,
        ]);

        $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(0, iterator_to_array($results));

        $store->drop();
    }

    public function testStoreCanAddThenSearch()
    {
        $store = new Store(sys_get_temp_dir(), 3);
        $store->setup();

        $store->add([
            new VectorDocument(Uuid::v4(), new Vector([0.1, 0.2, 0.3])),
        ]);

        $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])));

        $this->assertCount(1, iterator_to_array($results));

        $store->drop();
    }
}
