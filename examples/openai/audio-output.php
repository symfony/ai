<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\Voice;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$result = $platform->invoke('gpt-4o-mini-tts', 'Today is a wonderful day to build something people love!', [
    'voice' => Voice::CORAL,
    'instructions' => 'Speak in a cheerful and positive tone.',
]);

echo $result->asBinary();
