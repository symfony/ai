<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Sqlite\Tests;

use Symfony\AI\Store\Bridge\Sqlite\Store;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    protected static function createStore(): StoreInterface
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return new Store($pdo, 'test_vectors');
    }
}
