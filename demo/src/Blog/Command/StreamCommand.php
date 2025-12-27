<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Blog\Command;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:blog:stream', 'An example command to demonstrate streaming output.')]
final readonly class StreamCommand
{
    public function __construct(
        private AgentInterface $blog,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Stream Example Command');
        $io->text('This command demonstrates streaming output in the console.');

        $io->comment('Make sure to have ChromaDB running and the blog indexed, see README.');
        $io->comment('You can use -vvv or --profile to get more insights into the execution.');

        $question = $io->ask(
            'Ask a question about the content of the Symfony blog',
            'Tell me about the latest Symfony features.',
        );
        $messages = new MessageBag(Message::ofUser($question));

        $io->section('Agent Response:');
        $result = $this->blog->call($messages, ['stream' => true]);

        \assert($result instanceof StreamResult);

        foreach ($result->getContent() as $word) {
            $io->write($word);
        }

        $io->newLine(2);
        $io->success('The command has completed successfully.');

        return 0;
    }
}
