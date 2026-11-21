<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Neo4j\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Neo4j\StoreFactory;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    public function testDropKeepsTheRestOfTheDatabase()
    {
        $store = StoreFactory::create('neo4j', 'drop_test_index', 'DropTestNode', 'http://127.0.0.1:7474', 'neo4j', 'symfonyai', dimensions: 3);
        $store->setup();
        $store->add(new VectorDocument('1', new Vector([0.1, 0.2, 0.3])));

        $httpClient = HttpClient::createForBaseUri('http://127.0.0.1:7474/', ['auth_basic' => ['neo4j', 'symfonyai']]);
        $run = static fn (string $statement): array => $httpClient->request('POST', 'db/neo4j/query/v2', ['json' => ['statement' => $statement]])->toArray()['data']['values'];
        $run('CREATE (:DropTestOther)');

        try {
            $store->drop();

            $this->assertSame(0, $store->count());
            $this->assertSame([[1]], $run('MATCH (n:DropTestOther) RETURN count(n)'));
            $this->assertSame([], $run('SHOW VECTOR INDEXES YIELD name WHERE name = "drop_test_index" RETURN name'));
        } finally {
            $run('MATCH (n:DropTestOther) DELETE n');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getClearOptions(): array
    {
        // forces clear() to delete the volume test's nodes in several transactions
        return ['batch_size' => 100];
    }

    protected static function createStore(): StoreInterface
    {
        return StoreFactory::create(
            'neo4j',
            'test_vector_index',
            'TestNode',
            'http://127.0.0.1:7474',
            'neo4j',
            'symfonyai',
            dimensions: 3,
        );
    }
}
