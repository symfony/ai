<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\EdenAi\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

$result = $platform->invoke('audio/tts/amazon/neural', 'Welcome to Symfony AI, the PHP framework for building AI applications.');

$result->asFile(__DIR__.'/tts.mp3');

echo 'Audio saved to tts.mp3'.\PHP_EOL;
