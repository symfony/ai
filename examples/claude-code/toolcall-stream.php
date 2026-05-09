<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ClaudeCode\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    workingDirectory: dirname(__DIR__, 2),
    environment: ['CLAUDECODE' => false], // To enable Claude Code to execute the example
    logger: logger(),
);

$messages = new MessageBag(
    Message::ofUser('Read the top-level README.md and summarize it in two sentences. Tell me which tool you plan to use before calling it.'),
);
$result = $platform->invoke('sonnet', $messages, [
    'stream' => true,
    'permission_mode' => 'plan',
    'max_turns' => 2,
]);

foreach ($result->asStream() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta;
        continue;
    }
    if ($delta instanceof ToolCallStart) {
        output()->writeln(\PHP_EOL.'<info>[tool-call started: '.$delta->getName().']</info>');
        continue;
    }
    if ($delta instanceof ToolInputDelta) {
        output()->write('<fg=#999999>'.$delta->getPartialJson().'</>');
        continue;
    }
    if ($delta instanceof ToolCallComplete) {
        output()->writeln(\PHP_EOL.'<info>[tool-calls ready: '.count($delta->getToolCalls()).']</info>');
    }
}

echo \PHP_EOL;
