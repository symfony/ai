<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\VertexAi\Factory;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = Factory::createPlatform(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client());

$messages = new MessageBag(
    Message::ofUser(
        'Please colorize the elephant in red.',
        Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'),
    ),
);
$result = $platform->invoke('gemini-2.5-flash-image', $messages);

$result->asFile($path = __DIR__.'/result.png');

echo 'Result image saved to '.$path.\PHP_EOL;
