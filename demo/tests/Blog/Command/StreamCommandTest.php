<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Blog\Command;

use App\Blog\Command\StreamCommand;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class StreamCommandTest extends TestCase
{
    public function testStreamCommandOutputsStreamedContentAndSuccess()
    {
        $mockAgent = $this->createStub(AgentInterface::class);
        $mockAgent
            ->method('call')
            ->willReturn(new Execution(static function (): \Generator {
                yield new Progress('delta', 'Received a streamed delta.', new TextDelta('Hello'));
                yield new Progress('delta', 'Received a streamed delta.', new TextDelta(' '));
                yield new Progress('delta', 'Received a streamed delta.', new TextDelta('world'));
                yield new Progress('delta', 'Received a streamed delta.', new TextDelta('!'));
                yield new Result(new TextResult('Hello world!'));
            }, streamed: true));

        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $io = new SymfonyStyle($input, $buffer = new BufferedOutput());
        $command = new StreamCommand($mockAgent);
        $command->__invoke($io);

        $output = $buffer->fetch();

        $this->assertStringContainsString('Stream Example Command', $output);
        $this->assertStringContainsString('This command demonstrates streaming output', $output);
        $this->assertStringContainsString('Agent Response:', $output);
        $this->assertStringContainsString('Hello world!', $output);
        $this->assertStringContainsString('The command has completed successfully.', $output);
    }
}
