<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Command\PlatformInvokeCommand;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PlatformInvokeCommandTest extends TestCase
{
    public function testExecuteWithNonExistentPlatform()
    {
        $platforms = $this->createMock(ServiceLocator::class);
        $platforms->method('getProvidedServices')->willReturn(['openai' => 'service_class']);
        $platforms->method('has')->with('invalid')->willReturn(false);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform "invalid" not found. Available platforms: "openai"');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => 'invalid',
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithNoPlatformsConfigured()
    {
        $platforms = $this->createMock(ServiceLocator::class);
        $platforms->method('getProvidedServices')->willReturn([]);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No platforms are configured.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithEmptyMessage()
    {
        $platforms = $this->createMock(ServiceLocator::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $platforms->method('getProvidedServices')->willReturn(['openai' => 'service_class']);
        $platforms->method('has')->with('openai')->willReturn(true);
        $platforms->method('get')->with('openai')->willReturn($mockPlatform);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message is required.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => '',
        ]);
    }

    public function testInitializeValidatesEarly()
    {
        // Test that initialize method validates inputs before execute is called
        $platforms = $this->createMock(ServiceLocator::class);
        $platforms->method('getProvidedServices')->willReturn([]);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No platforms are configured.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => 'nonexistent',
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithWhitespaceOnlyPlatformName()
    {
        $platforms = $this->createMock(ServiceLocator::class);
        $platforms->method('getProvidedServices')->willReturn(['openai' => 'service_class']);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform name is required.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => '   ',  // Only whitespace
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithWhitespaceOnlyMessage()
    {
        $platforms = $this->createMock(ServiceLocator::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $platforms->method('getProvidedServices')->willReturn(['openai' => 'service_class']);
        $platforms->method('has')->with('openai')->willReturn(true);
        $platforms->method('get')->with('openai')->willReturn($mockPlatform);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message is required.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => '   ',  // Only whitespace
        ]);
    }

    public function testExecuteWithEmptyModel()
    {
        $platforms = $this->createMock(ServiceLocator::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $platforms->method('getProvidedServices')->willReturn(['openai' => 'service_class']);
        $platforms->method('has')->with('openai')->willReturn(true);
        $platforms->method('get')->with('openai')->willReturn($mockPlatform);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model is required.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => '',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithWhitespaceOnlyModel()
    {
        $platforms = $this->createMock(ServiceLocator::class);
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $platforms->method('getProvidedServices')->willReturn(['openai' => 'service_class']);
        $platforms->method('has')->with('openai')->willReturn(true);
        $platforms->method('get')->with('openai')->willReturn($mockPlatform);

        $command = new PlatformInvokeCommand($platforms);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model is required.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => '   ',  // Only whitespace
            'message' => 'Test message',
        ]);
    }

}



