<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Command\DropMessageStoreCommand;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DropMessageStoreCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new DropMessageStoreCommand(new ServiceLocator([]));

        $this->assertSame('ai:message-store:drop', $command->getName());
        $this->assertSame('Drop the required infrastructure for the message store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('store'));

        $storeArgument = $definition->getArgument('store');
        $this->assertSame('Name of the store to drop', $storeArgument->getDescription());
        $this->assertTrue($storeArgument->isRequired());
    }

    public function testCommandCannotDropUndefinedStore()
    {
        $command = new DropMessageStoreCommand(new ServiceLocator([]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" message store does not exist.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotDropInvalidStore()
    {
        $store = $this->createMock(MessageStoreInterface::class);

        $command = new DropMessageStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" message store does not support to be dropped.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotDropStoreWithException()
    {
        $store = $this->createMock(ManagedMessageStoreInterface::class);
        $store->expects($this->once())->method('drop')->willThrowException(new RuntimeException('foo'));

        $command = new DropMessageStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while dropping the "foo" message store: foo');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
            '--force' => true,
        ]);
    }

    public function testCommandCannotBeDroppedWithoutForceOption()
    {
        $store = $this->createMock(ManagedMessageStoreInterface::class);
        $store->expects($this->never())->method('drop');

        $command = new DropMessageStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
        ]);

        $this->assertStringContainsString('The --force option is required to drop the message store.', $tester->getDisplay());
    }

    public function testCommandCanDrop()
    {
        $store = $this->createMock(ManagedMessageStoreInterface::class);
        $store->expects($this->once())->method('drop');

        $command = new DropMessageStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
            '--force' => true,
        ]);

        $this->assertStringContainsString('The "foo" message store was dropped successfully.', $tester->getDisplay());
    }
}
