<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), http_client());

try {
    $result = $platform->invoke(env('OLLAMA_LLM'), new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
    ));

    echo $result->asText().\PHP_EOL;

    print_token_usage($result->getMetadata());
} catch (InvalidArgumentException $e) {
    echo $e->getMessage()."\nMaybe use a different model?\n";
}
