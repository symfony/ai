<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech\Format;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform('http://localhost:11434', optional_env('OLLAMA_API_KEY'), httpClient: http_client());

$result = $platform->invoke('phonon', 'Hello from Ollama, this is Symfony AI speaking.', [
    'voice' => 'Zoey',
    'response_format' => Format::WAV,
]);

echo $result->asBinary();
