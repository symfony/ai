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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Command\PlatformInvokeCommand;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PlatformInvokeCommandTest extends TestCase
{
    private MockObject&ServiceLocator $platforms;
    private PlatformInvokeCommand $command;

    protected function setUp(): void
    {
        $this->platforms = $this->createMock(ServiceLocator::class);
        $this->command = new PlatformInvokeCommand($this->platforms);
    }

    public function testExecuteWithNonExistentPlatform(): void
    {
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);
        $this->platforms->method('has')->with('ai.platform.invalid')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform "invalid" not found. Available platforms: "openai"');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'invalid',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithNoPlatformsConfigured(): void
    {
        $this->platforms->method('getProvidedServices')->willReturn([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No platforms are configured.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'openai',
            'message' => 'Test message',
        ]);
    }

    public function testExecuteWithEmptyMessage(): void
    {
        $this->platforms->method('getProvidedServices')->willReturn(['ai.platform.openai' => 'service_class']);
        $this->platforms->method('has')->with('ai.platform.openai')->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message is required.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'openai',
            'message' => '',
        ]);
    }

    public function testInitializeValidatesEarly(): void
    {
        // Test that initialize method validates inputs before execute is called
        $this->platforms->method('getProvidedServices')->willReturn([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No platforms are configured.');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'platform' => 'nonexistent',
            'message' => 'Test message',
        ]);
    }
}