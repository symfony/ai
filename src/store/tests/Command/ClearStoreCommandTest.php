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
use Symfony\AI\Store\Command\ClearStoreCommand;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ClearStoreCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new ClearStoreCommand(new ServiceLocator([]));

        $this->assertSame('ai:store:clear', $command->getName());
        $this->assertSame('Remove all documents from the store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('store'));
        $this->assertTrue($definition->hasOption('force'));

        $storeArgument = $definition->getArgument('store');
        $this->assertSame('Service name of the store to clear', $storeArgument->getDescription());
        $this->assertTrue($storeArgument->isRequired());

        $forceOption = $definition->getOption('force');
        $this->assertSame('Force clearing the store, required to actually remove the documents', $forceOption->getDescription());
        $this->assertFalse($forceOption->acceptValue());
    }

    public function testCommandCannotClearWithoutStores()
    {
        $command = new ClearStoreCommand(new ServiceLocator([]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No store is configured to be cleared.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotClearUndefinedStore()
    {
        $command = new ClearStoreCommand(new ServiceLocator([
            'bar' => fn (): object => $this->createMock(StoreInterface::class),
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" store does not exist, use "bar".');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotClearStoreWithException()
    {
        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())->method('clear')->willThrowException(new RuntimeException('foo'));

        $command = new ClearStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while clearing the "foo" store: foo');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
            '--force' => true,
        ]);
    }

    public function testCommandCannotBeClearedWithoutForceOption()
    {
        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->never())->method('clear');

        $command = new ClearStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
        ]);

        $this->assertStringContainsString('The --force option is required to clear the store.', $tester->getDisplay());
    }

    public function testCommandCanClear()
    {
        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())->method('clear');

        $command = new ClearStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
            '--force' => true,
        ]);

        $this->assertStringContainsString('The "foo" store was cleared successfully.', $tester->getDisplay());
    }
}
