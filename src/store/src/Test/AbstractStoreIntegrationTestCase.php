<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Test;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\LogicException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
abstract class AbstractStoreIntegrationTestCase extends TestCase
{
    private static ?StoreInterface $store = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$store instanceof ManagedStoreInterface) {
            self::$store->drop();
        }

        self::$store = null;
    }

    protected function setUp(): void
    {
        self::$store = static::createStore();

        if (self::$store instanceof ManagedStoreInterface) {
            self::$store->drop();
            self::$store->setup(static::getSetupOptions());
        }
    }

    public function testSetupAndDrop()
    {
        $store = static::createStore();

        if (!$store instanceof ManagedStoreInterface) {
            $this->markTestSkipped('Store does not implement ManagedStoreInterface.');
        }

        $store->setup(static::getSetupOptions());
        $store->drop();

        $this->addToAssertionCount(1);
    }

    public function testAddSingleDocument()
    {
        $id = Uuid::v4();
        $vector = new Vector([1.0, 0.0, 0.0]);
        $document = new VectorDocument($id, $vector);

        self::$store->add($document);

        $this->waitForIndexing();

        $results = iterator_to_array(self::$store->query($vector));

        $this->assertNotEmpty($results);

        $found = false;
        foreach ($results as $result) {
            if ((string) $result->id === (string) $id) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'The added document was not found in query results.');
    }

    public function testAddMultipleDocuments()
    {
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $vector1 = new Vector([1.0, 0.0, 0.0]);
        $vector2 = new Vector([0.0, 1.0, 0.0]);

        self::$store->add([
            new VectorDocument($id1, $vector1),
            new VectorDocument($id2, $vector2),
        ]);

        $this->waitForIndexing();

        $results1 = iterator_to_array(self::$store->query($vector1));
        $results2 = iterator_to_array(self::$store->query($vector2));

        $this->assertNotEmpty($results1);
        $this->assertNotEmpty($results2);

        $foundIds = [];
        foreach (array_merge($results1, $results2) as $result) {
            $foundIds[(string) $result->id] = true;
        }

        $this->assertArrayHasKey((string) $id1, $foundIds, 'First document was not found in query results.');
        $this->assertArrayHasKey((string) $id2, $foundIds, 'Second document was not found in query results.');
    }

    public function testQueryReturnsMetadata()
    {
        $id = Uuid::v4();
        $vector = new Vector([0.0, 0.0, 1.0]);
        $metadata = new Metadata(['key' => 'value']);
        $document = new VectorDocument($id, $vector, $metadata);

        self::$store->add($document);

        $this->waitForIndexing();

        $results = iterator_to_array(self::$store->query($vector));

        $this->assertNotEmpty($results);

        $found = null;
        foreach ($results as $result) {
            if ((string) $result->id === (string) $id) {
                $found = $result;
                break;
            }
        }

        $this->assertNotNull($found, 'The added document was not found in query results.');
        $this->assertSame('value', $found->metadata['key']);
    }

    public function testRemoveDocument()
    {
        $id = Uuid::v4();
        $vector = new Vector([1.0, 0.0, 0.0]);
        $document = new VectorDocument($id, $vector);

        self::$store->add($document);

        $this->waitForIndexing();

        try {
            self::$store->remove((string) $id);
        } catch (LogicException) {
            $this->markTestSkipped('Store does not support remove().');
        }

        $this->waitForIndexing();

        $results = iterator_to_array(self::$store->query($vector));

        foreach ($results as $result) {
            $this->assertNotSame((string) $id, (string) $result->id, 'The removed document was still found in query results.');
        }
    }

    abstract protected static function createStore(): StoreInterface;

    /**
     * @return array<string, mixed>
     */
    protected static function getSetupOptions(): array
    {
        return [];
    }

    protected function waitForIndexing(): void
    {
        // no-op by default, override in subclasses that need indexing time
    }
}
