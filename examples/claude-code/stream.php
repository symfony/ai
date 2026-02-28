<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ClaudeCode\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    workingDirectory: dirname(__DIR__, 2),
    environment: ['CLAUDECODE' => false], // To enable Claude Code to execute the example
    logger: logger(),
);

$messages = new MessageBag(
    Message::ofUser('What is Symfony? Explain in 3 sentences.'),
);
$result = $platform->invoke('haiku', $messages, [
    'stream' => true,
    'permission_mode' => 'plan',
    'max_turns' => 1,
]);

foreach ($result->asStream() as $word) {
    echo $word;
}
echo \PHP_EOL;
