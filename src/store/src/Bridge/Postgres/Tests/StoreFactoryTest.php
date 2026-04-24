<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Postgres\StoreFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class StoreFactoryTest extends TestCase
{
    public function testFromDbalWithNonPdoDriverThrowsException()
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('getNativeConnection')
            ->willReturn(new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only DBAL connections using PDO driver are supported.');
        $this->expectExceptionCode(0);
        StoreFactory::createStoreFromDbal($connection, 'test_table');
    }
}
