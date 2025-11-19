<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Command\SetupStoreCommand;
use Symfony\AI\Chat\Exception\RuntimeException;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class SetupStoreCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new SetupStoreCommand(new ServiceLocator([]));

        $this->assertSame('ai:message-store:setup', $command->getName());
        $this->assertSame('Prepare the required infrastructure for the message store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('store'));

        $storeArgument = $definition->getArgument('store');
        $this->assertSame('Name of the store to setup', $storeArgument->getDescription());
        $this->assertTrue($storeArgument->isRequired());
    }

    public function testCommandCannotSetupUndefinedStore()
    {
        $command = new SetupStoreCommand(new ServiceLocator([]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" message store does not exist.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotSetupInvalidStore()
    {
        $store = $this->createMock(MessageStoreInterface::class);

        $command = new SetupStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" message store does not support setup.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotSetupStoreWithException()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('setup')->willThrowException(new RuntimeException('foo'));

        $command = new SetupStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while setting up the "foo" message store: foo');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCanSetupDefinedStore()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('setup');

        $command = new SetupStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
        ]);

        $this->assertStringContainsString('The "foo" message store was set up successfully.', $tester->getDisplay());
    }
}
