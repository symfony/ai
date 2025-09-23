<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\AiMlApi\Completions;
use Symfony\AI\Platform\Bridge\AiMlApi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('AIMLAPI_API_KEY'), http_client());
$model = new Completions(Completions::GEMINI_2_0_FLASH);

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $platform->invoke($model, $messages, [
    'max_tokens' => 500, // specific options just for this call
]);

echo $result->getResult()->getContent().\PHP_EOL;
