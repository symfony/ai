<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Cartesia\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    apiKey: env('CARTESIA_API_KEY'),
    version: env('CARTESIA_API_VERSION'),
    httpClient: http_client(),
);

$result = $platform->invoke('sonic-3', new Text('Hello world'), [
    'voice' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b', // Tessa (https://play.cartesia.ai/voices/6ccbfb76-1fc6-48f7-b71d-91ac6298247b)
    'output_format' => [
        'container' => 'mp3',
        'sample_rate' => 48000,
        'bit_rate' => 192000,
    ],
]);

echo $result->asBinary().\PHP_EOL;
