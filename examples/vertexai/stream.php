<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), adc_aware_http_client());

$messages = new MessageBag(
    Message::forSystem('You are an expert assistant in geography.'),
    Message::ofUser('Where is Mount Fuji?'),
);

$result = $platform->invoke('gemini-2.5-flash', $messages, [
    'stream' => true,
]);

foreach ($result->asStream() as $word) {
    echo $word;
}
echo \PHP_EOL;
