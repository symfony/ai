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

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\Pinecone\Store;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    private const INDEX_NAME = 'test-index';

    protected static function createStore(): StoreInterface
    {
        // Pinecone Local ignores the API key, but the requests carry one
        $store = new Store(
            HttpClient::create(),
            'pclocal',
            self::INDEX_NAME,
            dimensions: 3,
            metric: 'cosine',
            cloud: 'aws',
            region: 'us-east-1',
            controlPlaneUrl: 'http://127.0.0.1:5080',
        );

        // The index outlives a previous run that did not reach drop(), and creating it again would fail
        try {
            $store->drop();
        } catch (ClientExceptionInterface $e) {
            if (404 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }
        }

        return $store;
    }

    protected function waitForIndexing(): void
    {
        sleep(2);
    }
}
