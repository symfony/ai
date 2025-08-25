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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Command\DropStoreCommand;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(DropStoreCommand::class)]
#[UsesClass(ManagedStoreInterface::class)]
#[UsesClass(StoreInterface::class)]
final class DropStoreCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new DropStoreCommand(new ServiceLocator([]), []);

        $this->assertSame('ai:store:drop', $command->getName());
        $this->assertSame('Drop the required infrastructure for the store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('store'));

        $storeArgument = $definition->getArgument('store');
        $this->assertSame('Name of the store to drop', $storeArgument->getDescription());
        $this->assertFalse($storeArgument->isRequired());
    }

    public function testCommandCannotSetupUndefinedStore()
    {
        $command = new DropStoreCommand(new ServiceLocator([]), []);

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" store does not exist.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotSetupInvalidStore()
    {
        $store = $this->createMock(StoreInterface::class);

        $command = new DropStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]), []);

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
        ]);

        $this->assertStringContainsString('The "foo" store does not support be dropped.', $tester->getDisplay());
    }

    public function testCommandCannotSetupStoreWithException()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('drop')->willThrowException(new RuntimeException('foo'));

        $command = new DropStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]), []);

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while dropping the "foo" store: foo');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCanSetupDefinedStore()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('drop');

        $command = new DropStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]), []);

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
        ]);

        $this->assertStringContainsString('The "foo" store was dropped successfully.', $tester->getDisplay());
    }
}
