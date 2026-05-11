<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\Factory;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENROUTER_KEY'), http_client());

// Note: The rendering takes about 1-2 minutes and cost about $ 0.12
// Suggest: Execute it with verbosity (-vv) to get information of the polling requests

$messages = new MessageBag(
    Message::forSystem('Animate the blue elephant.'),
    Message::ofUser(Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'))
);

$result = $platform->invoke('google/veo-3.1-lite', $messages, [
    'duration' => 4,
    'resolution' => '720p',
    'aspect_ratio' => '16:9',
    'generate_audio' => false,
]);

$targetFile = sys_get_temp_dir().'/openrouter-video-'.uniqid('', true).'.mp4';
$result->asFile($targetFile);

echo 'Video saved to: '.$targetFile.\PHP_EOL;
