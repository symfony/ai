<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Acp\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    workingDirectory: dirname(__DIR__, 2),
    command: $_SERVER['ACP_BINARY'] ?? $_ENV['ACP_BINARY'] ?? 'opencode acp',
    logger: logger(),
);

$messages = new MessageBag(
    Message::ofUser('What is Symfony? Explain in 3 sentences.'),
);

$result = $platform->invoke('acp-v1', $messages, ['stream' => true]);

foreach ($result->asTextStream() as $delta) {
    echo $delta;
}

echo \PHP_EOL;
