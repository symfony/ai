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

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENROUTER_KEY'), http_client(), modelCatalog: new SpeechModelCatalog());

$result = $platform->invoke('openai/gpt-4o-mini-tts-2025-12-15', 'Hello world from Symfony AI!', [
    'voice' => 'echo',
    'response_format' => 'mp3',
    'speed' => 1.0,
]);

$result->asFile('/tmp/openrouter-speech.mp3');
output()->writeln('Audio content saved to <comment>/tmp/openrouter-speech.mp3</comment>');
