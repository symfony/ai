<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Meilisearch\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\Meilisearch\StoreFactory;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    protected static function createStore(): StoreInterface
    {
        return StoreFactory::create(
            'test_index',
            'http://127.0.0.1:7700',
            'changeMe',
            embeddingsDimension: 3,
        );
    }

    protected function waitForIndexing(): void
    {
        // Meilisearch processes writes asynchronously, so a fixed sleep either wastes time or races the
        // task queue - depending on how many documents the test at hand wrote. Wait for the queue instead.
        $client = HttpClient::create();

        for ($attempt = 0; $attempt < 300; ++$attempt) {
            $response = $client->request('GET', 'http://127.0.0.1:7700/tasks', [
                'auth_bearer' => 'changeMe',
                'query' => [
                    'indexUids' => 'test_index',
                    'statuses' => 'enqueued,processing',
                ],
            ]);

            if ([] === $response->toArray()['results']) {
                return;
            }

            usleep(100_000);
        }

        $this->fail('Meilisearch did not finish processing its task queue in time.');
    }
}
