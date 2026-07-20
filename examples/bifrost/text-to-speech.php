<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bifrost\Audio\Voice;
use Symfony\AI\Platform\Bridge\Bifrost\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('BIFROST_API_KEY'), env('BIFROST_ENDPOINT'), http_client());

$result = $platform->invoke('openai/tts-1', 'Hello, welcome to Symfony AI on Bifrost!', [
    'voice' => Voice::ALLOY,
    'response_format' => 'mp3',
]);

echo $result->asBinary();
