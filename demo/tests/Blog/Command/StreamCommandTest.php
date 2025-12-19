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
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
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
            ->with($this->isInstanceOf(MessageBag::class), ['stream' => true])
            ->willReturn(new class implements ResultInterface {
                public function getContent(): iterable
                {
                    yield 'Hello';
                    yield ' ';
                    yield 'world';
                    yield '!';
                }

                public function getMetadata(): Metadata
                {
                    return new Metadata();
                }

                public function getRawResult(): ?RawResultInterface
                {
                    return null;
                }

                public function setRawResult(RawResultInterface $rawResult): void
                {
                }
            });

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
