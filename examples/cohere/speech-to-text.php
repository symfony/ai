<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Cohere\Factory;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('COHERE_API_KEY'), http_client());

$result = $platform->invoke('cohere-transcribe-03-2026', Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'), [
    'language' => 'en',
]);

echo $result->asText().\PHP_EOL;
