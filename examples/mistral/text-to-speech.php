<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\PlatformOptions;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('MISTRAL_API_KEY'), http_client());

$result = $platform->invoke('voxtral-mini-tts-3-0', new Text('Hello world'), [
    PlatformOptions::PROVIDER_OPTIONS => [
        'voice' => 'gb_jane_neutral',
    ],
]);

echo $result->asBinary();
