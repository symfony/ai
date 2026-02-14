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
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\MongoDb\Store;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    protected static function createStore(): StoreInterface
    {
        return new Store(
            new Client('mongodb://127.0.0.1:27017'),
            'test_database',
            'test_collection',
            'test_index',
            embeddingsDimension: 3,
        );
    }

    protected function waitForIndexing(): void
    {
        sleep(3);
    }
}
