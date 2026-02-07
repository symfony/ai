<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\MongoDb\Tests;

use MongoDB\Client;
use Symfony\AI\Store\Bridge\MongoDb\Store;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    private static ?Client $client = null;

    public static function tearDownAfterClass(): void
    {
        if (null !== self::$client) {
            self::$client->getCollection('test_database', 'test_collection')->drop();
        }

        self::$client = null;
    }

    protected function setUp(): void
    {
        $store = static::createStore();
        $store->drop();

        $collection = self::$client->getCollection('test_database', 'test_collection');
        $collection->createSearchIndex(
            [
                'fields' => [
                    [
                        'numDimensions' => 3,
                        'path' => 'vector',
                        'similarity' => 'euclidean',
                        'type' => 'vector',
                    ],
                ],
            ],
            [
                'name' => 'test_index',
                'type' => 'vectorSearch',
            ],
        );
    }

    protected static function createStore(): StoreInterface
    {
        self::$client = new Client('mongodb://127.0.0.1:27017');

        return new Store(
            self::$client,
            'test_database',
            'test_collection',
            'test_index',
        );
    }

    protected function waitForIndexing(): void
    {
        sleep(3);
    }
}
