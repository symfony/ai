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

$result = $platform->invoke('audio/speech_to_text_async/openai', 'https://raw.githubusercontent.com/symfony/ai/main/fixtures/audio.mp3', [
    'language' => 'en',
]);

echo $result->asText().\PHP_EOL;
