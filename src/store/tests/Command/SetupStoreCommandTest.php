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
use Symfony\AI\Store\Command\SetupStoreCommand;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class SetupStoreCommandTest extends TestCase
{
    public function testCommandIsConfigured()
    {
        $command = new SetupStoreCommand(new ServiceLocator([]));

        $this->assertSame('ai:store:setup', $command->getName());
        $this->assertSame('Prepare the required infrastructure for the store', $command->getDescription());

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('store'));

        $storeArgument = $definition->getArgument('store');
        $this->assertSame('Service name of the store to setup', $storeArgument->getDescription());
        $this->assertTrue($storeArgument->isRequired());

        $this->assertTrue($definition->getOption('option')->isArray());
    }

    public function testCommandCannotSetupUndefinedStore()
    {
        $command = new SetupStoreCommand(new ServiceLocator([]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No store is configured to be set up.');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCannotSetupWithoutStores()
    {
        $command = new SetupStoreCommand(new ServiceLocator([
            'bar' => fn (): object => $this->createMock(ManagedStoreInterface::class),
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" store does not exist, use "bar".');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ], ['interactive' => false]);
    }

    public function testCommandCannotSetupInvalidStore()
    {
        $store = $this->createMock(StoreInterface::class);

        $command = new SetupStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "foo" store does not support setup.');
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
        $this->expectExceptionMessage('An error occurred while setting up the "foo" store: foo');
        $this->expectExceptionCode(0);
        $tester->execute([
            'store' => 'foo',
        ]);
    }

    public function testCommandCanSetupDefinedStore()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('setup')->with([]);

        $command = new SetupStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
        ]);

        $this->assertStringContainsString('The "foo" store was set up successfully.', $tester->getDisplay());
    }

    public function testCommandPassesOptionsToTheStore()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('setup')->with([
            'forceDatabaseCreation' => true,
            'batchSize' => 100,
            'name' => 'foo',
            'flag' => true,
        ]);

        $command = new SetupStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $tester->execute([
            'store' => 'foo',
            '--option' => ['forceDatabaseCreation=true', 'batchSize=100', 'name=foo', 'flag'],
        ]);

        $this->assertStringContainsString('The "foo" store was set up successfully.', $tester->getDisplay());
    }

    public function testCommandRejectsOptionWithoutName()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->never())->method('setup');

        $command = new SetupStoreCommand(new ServiceLocator([
            'foo' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The option "=true" must be given as "name=value".');

        $tester->execute([
            'store' => 'foo',
            '--option' => ['=true'],
        ]);
    }

    public function testCommandSuggestsStoreNameForServiceId()
    {
        $command = new SetupStoreCommand(new ServiceLocator([
            'blog' => fn (): object => $this->createMock(ManagedStoreInterface::class),
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "ai.store.postgres.blog" store does not exist, did you mean "blog"?');
        $tester->execute([
            'store' => 'ai.store.postgres.blog',
        ], ['interactive' => false]);
    }

    public function testCommandDoesNotResolveServiceIdMatchingMultipleStores()
    {
        $command = new SetupStoreCommand(new ServiceLocator([
            'blog' => fn (): object => $this->createMock(ManagedStoreInterface::class),
            'postgres.blog' => fn (): object => $this->createMock(ManagedStoreInterface::class),
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "ai.store.postgres.blog" store does not exist, did you mean "blog", "postgres.blog"?');
        $tester->execute([
            'store' => 'ai.store.postgres.blog',
        ], ['interactive' => false]);
    }

    public function testCommandSuggestsAlternativesForUnknownStore()
    {
        $command = new SetupStoreCommand(new ServiceLocator([
            'default' => fn (): object => $this->createMock(ManagedStoreInterface::class),
            'blog' => fn (): object => $this->createMock(ManagedStoreInterface::class),
        ]));

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "blgo" store does not exist, did you mean "blog"?');
        $tester->execute([
            'store' => 'blgo',
        ], ['interactive' => false]);
    }

    public function testCommandAsksForMissingStore()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('setup');

        $command = new SetupStoreCommand(new ServiceLocator([
            'default' => fn (): object => $this->createMock(ManagedStoreInterface::class),
            'blog' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);
        $tester->setInputs(['blog']);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Which store do you want to use?', $display);
        $this->assertStringContainsString('The "blog" store was set up successfully.', $display);
    }

    public function testCommandProposesAlternativesForUnknownStore()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('setup');

        $command = new SetupStoreCommand(new ServiceLocator([
            'default' => fn (): object => $this->createMock(ManagedStoreInterface::class),
            'blog' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);
        $tester->setInputs(['']);
        $tester->execute([
            'store' => 'blgo',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('The "blgo" store does not exist.', $display);
        $this->assertStringContainsString('[blog]', $display);
        $this->assertStringContainsString('The "blog" store was set up successfully.', $display);
    }

    public function testCommandSelectsStoreByChoiceIndex()
    {
        $store = $this->createMock(ManagedStoreInterface::class);
        $store->expects($this->once())->method('setup');

        $command = new SetupStoreCommand(new ServiceLocator([
            'default' => fn (): object => $this->createMock(ManagedStoreInterface::class),
            'blog' => static fn (): object => $store,
        ]));

        $tester = new CommandTester($command);
        $tester->setInputs(['1']);
        $tester->execute([]);

        $this->assertStringContainsString('The "blog" store was set up successfully.', $tester->getDisplay());
    }
}
