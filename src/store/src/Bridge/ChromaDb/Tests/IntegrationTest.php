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
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\ChromaDb\StoreFactory;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    #[Depends('testAddDocuments')]
    public function testQueryDocumentsWithTextQuery()
    {
        // ChromaDb TextQuery requires an embedding function to be configured, but this test must not
        // be skipped, since the tests depending on it would be skipped as well, and the store would
        // never get to the point of being removed from, cleared or dropped
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getClearOptions(): array
    {
        // forces clear() to paginate through the volume test's documents in several rounds
        return ['batch_size' => 100];
    }

    protected static function createStore(): StoreInterface
    {
        $client = ChromaDB::factory()
            ->withHost('http://127.0.0.1')
            ->withPort(8000)
            ->connect();

        return StoreFactory::create($client, 'test_collection');
    }
}
