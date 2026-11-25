<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ovh\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OVH_AI_SECRET_KEY'), http_client());

$result = $platform->invoke('nvr-tts-en-us', 'Hello world from Symfony AI!', [
    'voice' => 'English-US.Male-1',
]);

$result->asFile(__DIR__.'/speech.wav');

echo 'Audio saved to speech.wav'.\PHP_EOL;
