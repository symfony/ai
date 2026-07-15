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
use Symfony\AI\Store\Bridge\Neo4j\Store;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
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
        return new Store(
            HttpClient::create(),
            'http://127.0.0.1:7474',
            'neo4j',
            'symfonyai',
            'neo4j',
            'test_vector_index',
            'TestNode',
            embeddingsDimension: 3,
        );
    }
}
