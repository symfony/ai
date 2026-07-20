<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\HelixDb\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\HelixDb\Store;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Requires a running HelixDB instance with the bridge's "Resources/*.hx" queries deployed.
 *
 * Unlike stores that provision their schema in setup(), HelixDB expects the HelixQL queries to be
 * compiled and deployed out of band (see the bridge README), which the shared integration workflow
 * cannot do. So when the instance is unreachable or the queries are not deployed, the whole suite is
 * skipped rather than erroring, and it runs in full against a properly provisioned local instance.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    public function testSetupStore()
    {
        try {
            parent::testSetupStore();
        } catch (RuntimeException $exception) {
            $this->markTestSkipped($exception->getMessage());
        }
    }

    protected static function createStore(): StoreInterface
    {
        return new Store(
            HttpClient::create(),
            'http://127.0.0.1:6969',
            embeddingsDimension: 3,
        );
    }
}
