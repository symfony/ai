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
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\ChromaDb\Store;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    public function testQueryDocumentsWithTextQuery()
    {
        $this->markTestSkipped('ChromaDb TextQuery requires an embedding function to be configured.');
    }

    protected static function createStore(): StoreInterface
    {
        $client = ChromaDB::factory()
            ->withHost('http://127.0.0.1')
            ->withPort(8000)
            ->connect();

        return new Store($client, 'test_collection');
    }
}
