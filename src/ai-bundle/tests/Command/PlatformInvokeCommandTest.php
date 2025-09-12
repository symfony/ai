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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Command\PlatformInvokeCommand;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(PlatformInvokeCommand::class)]
#[UsesClass(InvalidArgumentException::class)]
final class PlatformInvokeCommandTest extends TestCase
{
    private MockObject&ServiceLocator $platforms;
    private PlatformInvokeCommand $command;

    protected function setUp(): void
    {
        $this->platforms = $this->createMock(ServiceLocator::class);
        $this->command = new PlatformInvokeCommand($this->platforms);
    }

    public function testExecuteWithNonExistentPlatform()
    {
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);
        $this->platforms->method('has')->with('ai.platform.invalid')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform "invalid" not found. Available platforms: "openai"');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'invalid',
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithNoPlatformsConfigured()
    {
        $this->platforms->method('getProvidedServices')->willReturn([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No platforms are configured.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithEmptyMessage()
    {
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);
        $this->platforms->method('has')->with('ai.platform.openai')->willReturn(true);
        $this->platforms->method('get')->with('ai.platform.openai')->willReturn($mockPlatform);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message is required.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => '',
        ]);
    }

    public function testInitializeValidatesEarly()
    {
        // Test that initialize method validates inputs before execute is called
        $this->platforms->method('getProvidedServices')->willReturn([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No platforms are configured.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'nonexistent',
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithWhitespaceOnlyPlatformName()
    {
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform name is required.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => '   ',  // Only whitespace
            'model' => 'gpt-4o-mini',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithWhitespaceOnlyMessage()
    {
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);
        $this->platforms->method('has')->with('ai.platform.openai')->willReturn(true);
        $this->platforms->method('get')->with('ai.platform.openai')->willReturn($mockPlatform);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message is required.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => '   ',  // Only whitespace
        ]);
    }

    public function testExecuteWithEmptyModel()
    {
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);
        $this->platforms->method('has')->with('ai.platform.openai')->willReturn(true);
        $this->platforms->method('get')->with('ai.platform.openai')->willReturn($mockPlatform);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model is required.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => '',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithWhitespaceOnlyModel()
    {
        $mockPlatform = $this->createMock(PlatformInterface::class);
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);
        $this->platforms->method('has')->with('ai.platform.openai')->willReturn(true);
        $this->platforms->method('get')->with('ai.platform.openai')->willReturn($mockPlatform);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model is required.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'openai',
            'model' => '   ',  // Only whitespace
            'message' => 'Test message',
        ]);
    }
}