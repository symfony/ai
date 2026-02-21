<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Qdrant\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Store\Bridge\Qdrant\StoreFactory;
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
        return StoreFactory::create(
            'test_collection',
            'http://127.0.0.1:6333',
            'changeMe',
            embeddingsDimension: 3,
        );
    }
}
