<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\AmazeeAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('AMAZEEAI_LLM_API_URL'), env('AMAZEEAI_LLM_KEY'), http_client());

$messages = new MessageBag(Message::ofUser('List the first 50 prime number?'));
$result = $platform->invoke('claude-3-5-haiku', $messages, [
    'stream' => true,
]);

foreach ($result->asStream() as $word) {
    echo $word;
}
echo \PHP_EOL;
