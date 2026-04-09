<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Deepgram\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::createPlatform(apiKey: env('DEEPGRAM_API_KEY'), httpClient: http_client());

$result = $platform->invoke('nova-3', Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'));

echo $result->asText().\PHP_EOL;
