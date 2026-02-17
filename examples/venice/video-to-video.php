<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Venice\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('VENICE_API_KEY'), httpClient: http_client());

$result = $platform->invoke(
    'runway-gen4-aleph',
    [
        'prompt' => 'Restyle as anime, neon lighting, cinematic camera',
        'video_url' => 'https://example.com/source.mp4',
    ],
    ['duration' => '8s', 'aspect_ratio' => '16:9'],
);

$result->asFile(__DIR__.'/restyled.mp4');
