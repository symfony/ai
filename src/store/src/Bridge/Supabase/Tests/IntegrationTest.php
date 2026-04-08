<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Supabase\Tests;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Supabase\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Does not extend {@see AbstractStoreIntegrationTestCase} because the Supabase
 * store does not implement {@see ManagedStoreInterface}. The database schema is
 * provisioned via the init.sql fixture, matching how Supabase users manage
 * schemas through migrations rather than through the REST API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends TestCase
{
    private const DOCUMENT_ID_1 = '367e550e-6c92-4f12-8a6b-3f3f1d5e8c9a';
    private const DOCUMENT_ID_2 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const DOCUMENT_ID_3 = '123e4567-e89b-12d3-a456-426614174000';

    private static Store $store;

    public function testAddDocuments()
    {
        self::$store = self::createStore();

        self::$store->add(new VectorDocument(self::DOCUMENT_ID_1, new Vector([1.0, 0.0, 0.0]), new Metadata(['name' => 'first document'])));
        self::$store->add([
            new VectorDocument(self::DOCUMENT_ID_2, new Vector([0.0, 1.0, 0.0]), new Metadata(['name' => 'second document'])),
            new VectorDocument(self::DOCUMENT_ID_3, new Vector([0.0, 0.0, 1.0]), new Metadata(['name' => 'third document'])),
        ]);

        $this->addToAssertionCount(1);
    }

    #[Depends('testAddDocuments')]
    public function testQueryDocuments()
    {
        $results = self::$store->query(new VectorQuery(new Vector([0.0, 0.0, 1.0])));

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
        self::$store->remove(self::DOCUMENT_ID_3);

        $results = self::$store->query(new VectorQuery(new Vector([0.0, 0.0, 1.0])));

        foreach ($results as $result) {
            $this->assertNotSame(self::DOCUMENT_ID_3, $result->getId());
        }
    }

    private static function createStore(): Store
    {
        return new Store(
            HttpClient::create(),
            'http://127.0.0.1:3000',
            '',
            'documents',
            'embedding',
            3,
            'match_documents',
        );
    }
}
