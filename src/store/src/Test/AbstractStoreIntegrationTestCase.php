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

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\UnsupportedFeatureException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractStoreIntegrationTestCase extends TestCase
{
    private const DOCUMENT_ID_1 = '367e550e-6c92-4f12-8a6b-3f3f1d5e8c9a';
    private const DOCUMENT_ID_2 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const DOCUMENT_ID_3 = '123e4567-e89b-12d3-a456-426614174000';

    private static ?StoreInterface $store = null;

    public function testSetupStore()
    {
        self::$store = static::createStore();

        if (!self::$store instanceof ManagedStoreInterface) {
            $this->markTestSkipped('Store does not implement ManagedStoreInterface.');
        }

        self::$store->setup(static::getSetupOptions());

        $this->addToAssertionCount(1);
    }

    #[Depends('testSetupStore')]
    public function testAddDocuments()
    {
        // Add single document
        self::$store->add(new VectorDocument(self::DOCUMENT_ID_1, new Vector([1.0, 0.0, 0.0]), new Metadata(['name' => 'first document'])));
        // Add multiple documents
        self::$store->add([
            new VectorDocument(self::DOCUMENT_ID_2, new Vector([0.0, 1.0, 0.0]), new Metadata(['name' => 'second document'])),
            new VectorDocument(self::DOCUMENT_ID_3, new Vector([0.0, 0.0, 1.0]), new Metadata(['name' => 'third document'])),
        ]);

        $this->waitForIndexing();

        $this->addToAssertionCount(1);
    }

    #[Depends('testAddDocuments')]
    public function testQueryDocuments()
    {
        $results = self::$store->query(new Vector([0.0, 0.0, 1.0]));

        $found = null;
        foreach ($results as $result) {
            if (self::DOCUMENT_ID_3 === $result->getId()) {
                $found = $result;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertSame('third document', $found->getMetadata()['name']);
    }

    #[Depends('testQueryDocuments')]
    public function testRemoveDocuments()
    {
        try {
            self::$store->remove(self::DOCUMENT_ID_3);
        } catch (UnsupportedFeatureException) {
            $this->markTestSkipped('Store does not support remove().');
        }

        $this->waitForIndexing();

        $results = self::$store->query(new Vector([0.0, 0.0, 1.0]));

        foreach ($results as $result) {
            $this->assertNotSame(self::DOCUMENT_ID_3, $result->getId());
        }
    }

    #[Depends('testRemoveDocuments')]
    public function testDropStore()
    {
        if (!self::$store instanceof ManagedStoreInterface) {
            $this->markTestSkipped('Store does not implement ManagedStoreInterface.');
        }

        self::$store->drop();

        $this->addToAssertionCount(1);
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
