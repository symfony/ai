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
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Symfony\AI\Store\Bridge\Pinecone\Store;
use Symfony\AI\Store\Bridge\Pinecone\Tests\Fixtures\PineconeLocalClient;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Test\AbstractStoreIntegrationTestCase;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[Group('integration')]
final class IntegrationTest extends AbstractStoreIntegrationTestCase
{
    private const INDEX_NAME = 'test-index';

    protected static function createStore(): StoreInterface
    {
        // Pinecone Local ignores the API key, but the client requires one
        $store = new Store(
            new PineconeLocalClient('pclocal', 'http://127.0.0.1:5080', self::INDEX_NAME),
            self::INDEX_NAME,
        );

        // The index outlives a previous run that did not reach drop(), and creating it again would fail
        try {
            $store->drop();
        } catch (NotFoundException) {
        }

        return $store;
    }

    protected static function getSetupOptions(): array
    {
        return [
            'dimension' => 3,
            'metric' => 'cosine',
            'cloud' => 'aws',
            'region' => 'us-east-1',
        ];
    }

    protected function waitForIndexing(): void
    {
        sleep(2);
    }
}
