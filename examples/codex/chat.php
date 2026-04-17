<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Codex\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    workingDirectory: dirname(__DIR__, 2),
    environment: ['CODEX' => false], // To enable Codex to execute the example
    logger: logger(),
);

$messages = new MessageBag(
    Message::ofUser('Explain the architecture of this project in 3 sentences.'),
);
$result = $platform->invoke('gpt-5-codex', $messages, [
    'sandbox' => 'read-only',
]);

echo $result->asText().\PHP_EOL;

print_token_usage($result->getMetadata()->get('token_usage'));
