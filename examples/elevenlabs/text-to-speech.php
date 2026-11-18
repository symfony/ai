<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ElevenLabs\Factory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\PlatformOptions;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(apiKey: env('ELEVEN_LABS_API_KEY'), httpClient: http_client());

$result = $platform->invoke('eleven_multilingual_v2', new Text('Hello world'), [
    PlatformOptions::PROVIDER_OPTIONS => [
        'voice' => 'pqHfZKP75CvOlQylNhV4', // Bill
    ],
]);

echo $result->asBinary();
