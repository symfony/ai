<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\MiniMax\Factory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\PlatformOptions;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('MINI_MAX_API_KEY'), http_client());

$result = $platform->invoke('image-01', new Text('A cat on a kitchen table'), [
    PlatformOptions::PROVIDER_OPTIONS => [
        'aspect_ratio' => '16:9',
    ],
]);

$result->asFile(__DIR__.'/text-to-image.jpg');

echo 'Image saved to text-to-image.jpg'.\PHP_EOL;
