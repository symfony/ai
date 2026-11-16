<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Redis\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Redis\Store;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    public function testQueryReturnsAsManyDocumentsAsTheLimit()
    {
        // own index and key prefix, so the documents stay out of the shared store of the parent test case
        $store = new Store(self::createRedis(), 'test_index_limit', 'vector_limit:', dimensions: 3);
        $store->setup();

        try {
            $documents = [];
            for ($i = 0; $i < 8; ++$i) {
                $documents[] = new VectorDocument(Uuid::v4()->toRfc4122(), new Vector([1.0, $i / 8, 0.0]));
            }
            $store->add($documents);

            $results = $store->query(new VectorQuery(new Vector([1.0, 0.0, 0.0])), ['limit' => 8]);

            $this->assertCount(8, iterator_to_array($results, false));
        } finally {
            $store->clear();
            $store->drop();
        }
    }

    protected static function createStore(): StoreInterface
    {
        return new Store(self::createRedis(), 'test_index', dimensions: 3);
    }

    private static function createRedis(): \Redis
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);

        return $redis;
    }
}
