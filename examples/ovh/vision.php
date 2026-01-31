<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ovh\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OVH_AI_SECRET_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser(
        'Describe this image in 1 sentence. What is the object in the image?',
        Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'),
    ),
);
$result = $platform->invoke('Qwen2.5-VL-72B-Instruct', $messages);

echo $result->asText().\PHP_EOL;
