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

use Symfony\AI\Store\Bridge\Sqlite\Distance;
use Symfony\AI\Store\Bridge\Sqlite\VecStore;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

final class VecStoreIntegrationTest extends AbstractStoreIntegrationTestCase
{
    protected static function createStore(): StoreInterface
    {
        $extensionPath = $_SERVER['SQLITE_VEC_PATH'] ?? $_ENV['SQLITE_VEC_PATH'] ?? null;

        if (null !== $extensionPath && file_exists($extensionPath) && class_exists(\Pdo\Sqlite::class)) {
            $pdo = new \Pdo\Sqlite('sqlite::memory:');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->loadExtension($extensionPath);
        } else {
            $pdo = new \PDO('sqlite::memory:');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        if (!VecStore::isExtensionAvailable($pdo)) {
            static::markTestSkipped('sqlite-vec extension is not available.');
        }

        return new VecStore($pdo, 'test_vectors', Distance::Cosine, 3);
    }
}
