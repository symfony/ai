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
use Symfony\AI\Platform\Bridge\OpenRouter\Speech\SpeechModelCatalog;
use Symfony\AI\Platform\PlatformOptions;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENROUTER_KEY'), http_client(), modelCatalog: new SpeechModelCatalog());

$result = $platform->invoke('google/gemini-3.1-flash-tts-preview', 'Hello world from Symfony AI!', [
    PlatformOptions::PROVIDER_OPTIONS => [
        'voice' => 'Zephyr',
        'response_format' => 'pcm',
    ],
]);

echo $result->asBinary();
