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
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$eventDispatcher = new EventDispatcher();
$eventDispatcher->addListener(ToolCallRequested::class, static function (ToolCallRequested $event): void {
    output()->write(sprintf('Allow tool "%s"? [y/N] ', $event->getToolCall()->getName()));

    if ('y' !== strtolower(trim(fgets(\STDIN)))) {
        $event->deny('User denied tool execution.');
    }
});

$toolbox = new Toolbox([new Filesystem(new SymfonyFilesystem(), __DIR__)], logger: logger(), eventDispatcher: $eventDispatcher);
$processor = new AgentProcessor($toolbox, eventDispatcher: $eventDispatcher);
$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor]);

$messages = new MessageBag(Message::ofUser('First, list the files in this folder. Then delete the file confirmation.php'));

$result = $agent->call($messages, ['stream' => true]);

foreach ($result->getContent() as $chunk) {
    echo $chunk;
}

echo \PHP_EOL;
