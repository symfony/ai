<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\Postgres\Metric;
use Symfony\AI\Store\Bridge\Postgres\Store;
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
            'pgsql:host=127.0.0.1;port=5432;dbname=test_database',
            'test_vectors',
            'embedding',
            Metric::L2,
            'english',
            3,
            'vector',
            'hnsw',
            null,
            ['db_username' => 'postgres', 'db_password' => 'postgres'],
        );
    }
}
