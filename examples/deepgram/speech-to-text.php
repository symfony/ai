<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Deepgram\Factory;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\PlatformOptions;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(apiKey: env('DEEPGRAM_API_KEY'), httpClient: http_client());

// "multi" enables nova-3 multilingual (code-switching) transcription
$result = $platform->invoke('nova-3', Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'), [PlatformOptions::PROVIDER_OPTIONS => ['language' => 'multi']]);

echo $result->asText().\PHP_EOL;
