<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ChromaDb\Tests;

use Codewithkyrian\ChromaDB\ChromaDB;
use Codewithkyrian\ChromaDB\Client;
use Symfony\AI\Store\Bridge\ChromaDb\Store;
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
            try {
                self::$client->deleteCollection('test_collection');
            } catch (\Exception) {
                // collection may not exist
            }
        }

        self::$client = null;
    }

    protected function setUp(): void
    {
        self::$client = ChromaDB::client('127.0.0.1', 8000);

        try {
            self::$client->deleteCollection('test_collection');
        } catch (\Exception) {
            // collection may not exist yet
        }
    }

    protected static function createStore(): StoreInterface
    {
        self::$client = ChromaDB::client('127.0.0.1', 8000);

        return new Store(self::$client, 'test_collection');
    }
}
