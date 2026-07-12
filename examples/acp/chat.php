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
    command: env('ACP_BINARY'),
    logger: logger(),
    onStatus: static function (string $status): void {
        output()->writeln(sprintf('<info>[acp] %s</info>', $status));
    },
);

$messages = new MessageBag(
    Message::ofUser('Explain the architecture of this project in 3 sentences.'),
);

$result = $platform->invoke('acp-v1', $messages);

echo $result->asText().\PHP_EOL;

$tokenUsage = $result->getMetadata()->get('token_usage');
if (null !== $tokenUsage) {
    print_token_usage($tokenUsage);
    echo \PHP_EOL;
}
