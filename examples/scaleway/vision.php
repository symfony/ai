<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Scaleway\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('SCALEWAY_SECRET_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser(
        'Describe this image in 1 sentence. What is the object in the image?',
        Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'),
    ),
);
$result = $platform->invoke('pixtral-12b-2409', $messages);

echo $result->getResult()->getContent().\PHP_EOL;
