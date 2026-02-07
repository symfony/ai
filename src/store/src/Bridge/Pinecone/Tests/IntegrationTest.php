<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone\Tests;

use Probots\Pinecone\Client;
use Symfony\AI\Store\Bridge\Pinecone\Store;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    protected static function createStore(): StoreInterface
    {
        $client = new Client('test-api-key', 'http://localhost:5080');

        return new Store($client, 'test-index');
    }

    protected static function getSetupOptions(): array
    {
        return ['dimension' => 3];
    }
}
