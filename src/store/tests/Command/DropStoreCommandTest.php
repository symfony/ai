<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Command\DropStoreCommand;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DropStoreCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new DropStoreCommand(new ServiceLocator([]));

        $this->assertSame('ai:store:drop', $command->getName());
        $this->assertSame('Drop the required infrastructure for the store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('store'));

        $storeArgument = $definition->getArgument('store');
        $this->assertSame('Service name of the store to drop', $storeArgument->getDescription());
        $this->assertTrue($storeArgument->isRequired());
    }

    public function testCommandCannotDropWithoutStores()
    {
        $command = new DropStoreCommand(new ServiceLocator([]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No store is configured to be dropped.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotDropUndefinedStore()
    {
        $command = new DropStoreCommand(new ServiceLocator([
            'bar' => fn (): object => $this->createMock(ManagedStoreInterface::class),
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" store does not exist, use "bar".');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotDropInvalidStore()
    {
        $store = $this->createMock(StoreInterface::class);

        $command = new DropStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" store does not support to be dropped.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotDropStoreWithException()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('drop')->willThrowException(new RuntimeException('foo'));

        $command = new DropStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while dropping the "foo" store: foo');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
            '--force' => true,
        ]);
    }

    public function testCommandCannotBeDroppedWithoutForceOption()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->never())->method('drop');

        $command = new DropStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
        ]);

        $this->assertStringContainsString('The --force option is required to drop the store.', $tester->getDisplay());
    }

    public function testCommandCanDrop()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('drop');

        $command = new DropStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
            '--force' => true,
        ]);

        $this->assertStringContainsString('The "foo" store was dropped successfully.', $tester->getDisplay());
    }
}
