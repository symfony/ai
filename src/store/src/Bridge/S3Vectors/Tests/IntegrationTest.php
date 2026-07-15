<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\S3Vectors\Tests;

use AsyncAws\S3Vectors\Exception\NotFoundException;
use AsyncAws\S3Vectors\S3VectorsClient;
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\S3Vectors\Store;
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
        // LocalS3 ignores the credentials, but the client requires them
        $store = new Store(
            new S3VectorsClient([
                'endpoint' => 'http://127.0.0.1:8099',
                'region' => 'us-east-1',
                'accessKeyId' => 'test',
                'accessKeySecret' => 'test',
            ]),
            'test-bucket',
            'test-index',
        );

        // Bucket and index outlive a previous run that did not reach drop(), and creating them again would fail
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getClearOptions(): array
    {
        // clear() lists the vectors page by page, and DeleteVectors only accepts 500 keys per call, so a
        // page is deleted in several calls once it grows beyond that. A page of 1.000 keys covers that
        // chunking in the high volume run, while the smaller page makes the regular run walk several pages.
        return ['batch_size' => self::isHighVolumeRun() ? 1_000 : 100];
    }
}
