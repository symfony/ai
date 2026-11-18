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

$result = $platform->invoke('music-1.5', new Text('A cheerful pop song with an upbeat melody'), [
    PlatformOptions::PROVIDER_OPTIONS => [
        'lyrics' => "##\nWe are dancing in the light\nEverything is gonna be alright\n##",
        'audio_setting' => [
            'sample_rate' => 44100,
            'bitrate' => 256000,
            'format' => 'mp3',
        ],
    ],
]);

echo $result->asBinary();
