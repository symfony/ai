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
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PlatformInvokeCommandTest extends TestCase
{
    public function testExecuteSuccessfully()
    {
        $textResult = new TextResult('Hello! How can I assist you?');
        $rawResult = new InMemoryRawResult([]);
        $promise = new DeferredResult(new PlainConverter($textResult), $rawResult);

        $platform = $this->createMock(PlatformInterface::class);
        $platform->method('invoke')
            ->with('gpt-4o-mini', $this->anything())
            ->willReturn($promise);

        $platforms = $this->createMock(ServiceLocator::class);
        $platforms->method('getProvidedServices')->willReturn(['openai' => 'service_class']);
        $platforms->method('has')->with('openai')->willReturn(true);
        $platforms->method('get')->with('openai')->willReturn($platform);

        $command = new PlatformInvokeCommand($platforms);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'platform' => 'openai',
            'model' => 'gpt-4o-mini',
            'message' => 'Hello!',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Response:', $commandTester->getDisplay());
        $this->assertStringContainsString('Hello! How can I assist you?', $commandTester->getDisplay());
    }

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
}
