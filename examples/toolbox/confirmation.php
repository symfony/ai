<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Bridge\Filesystem\Filesystem;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationHandlerInterface;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationResult;
use Symfony\AI\Agent\Toolbox\Confirmation\ConfirmationSubscriber;
use Symfony\AI\Agent\Toolbox\Confirmation\DefaultPolicy;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

require_once dirname(__DIR__).'/bootstrap.php';

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addSubscriber(new ConfirmationSubscriber(new DefaultPolicy(), new class implements ConfirmationHandlerInterface {
    public function requestConfirmation(ToolCall $toolCall): ConfirmationResult
    {
        $args = json_encode($toolCall->getArguments());
        output()->writeln(sprintf('🔐 Tool "%s" wants to execute with args: %s', $toolCall->getName(), $args));
        $question = new ChoiceQuestion('Do you want to allow this?', ['y', 'N', 'always', 'never'], 1);

        return match ((new QuestionHelper())->ask(new ArgvInput(), output(), $question)) {
            'y' => ConfirmationResult::confirmed(),
            'always' => ConfirmationResult::always(),
            'never' => ConfirmationResult::never(),
            default => ConfirmationResult::denied(),
        };
    }
}));

$toolbox = new Toolbox(
    [new Filesystem(new SymfonyFilesystem(), __DIR__)],
    logger: logger(),
    eventDispatcher: $eventDispatcher,
);

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$processor = new AgentProcessor($toolbox, eventDispatcher: $eventDispatcher);
$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor]);

output()->writeln('Human-in-the-Loop Tool Confirmation Demo');
output()->writeln('=========================================');
output()->writeln('The DefaultPolicy auto-allows read operations (filesystem_list) but asks for write operations (filesystem_delete).');

$messages = new MessageBag(Message::ofUser(
    'First, list the files in this folder. Then delete the file confirmation.php',
));

$result = $agent->call($messages, ['stream' => true]);

foreach ($result->getContent() as $chunk) {
    echo $chunk;
}

echo \PHP_EOL;
