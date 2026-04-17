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

// Use a temporary directory as working directory so Codex can write files.
// Codex requires a git repository, so we initialize one.
$workingDirectory = sys_get_temp_dir().'/codex-example-'.bin2hex(random_bytes(4));
mkdir($workingDirectory);
shell_exec(sprintf('git -C %s init --quiet', escapeshellarg($workingDirectory)));

$platform = Factory::createPlatform(
    workingDirectory: $workingDirectory,
    environment: ['CODEX' => false],
    logger: logger(),
);

$messages = new MessageBag(
    Message::ofUser('Create a file called hello.php that prints "Hello, World!" to the console.'),
);
$result = $platform->invoke('gpt-5-codex', $messages, [
    'sandbox' => 'workspace-write',
]);

echo $result->asText().\PHP_EOL;

$helloFile = $workingDirectory.'/hello.php';
if (file_exists($helloFile)) {
    echo \PHP_EOL.'--- Generated file content ---'.\PHP_EOL;
    echo file_get_contents($helloFile);
    echo \PHP_EOL.'--- Executing generated file ---'.\PHP_EOL;
    passthru('php '.escapeshellarg($helloFile));
}

// Cleanup
array_map('unlink', glob($workingDirectory.'/*'));
shell_exec(sprintf('rm -rf %s', escapeshellarg($workingDirectory.'/.git')));
rmdir($workingDirectory);

print_token_usage($result->getMetadata()->get('token_usage'));
