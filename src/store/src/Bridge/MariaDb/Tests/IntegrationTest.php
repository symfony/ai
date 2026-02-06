<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\MariaDb\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\MariaDb\Store;
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
        $pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=test_database', 'root', '');

        return new Store($pdo, 'test_vectors', 'test_vector_idx', 'embedding');
    }

    protected static function getSetupOptions(): array
    {
        return ['dimensions' => 3];
    }
}
