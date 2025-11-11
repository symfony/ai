<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Exception\RuntimeException;

require_once dirname(__DIR__).'/bootstrap.php';

throw new RuntimeException('This example is temporarily unavailable due to migration to Responses API (which does not support audio yet).');
// $platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
//
// $messages = new MessageBag(
//    Message::ofUser(
//        'What is this recording about?',
//        Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'),
//    ),
// );
// $result = $platform->invoke('gpt-4o-audio-preview', $messages);
//
// echo $result->asText().\PHP_EOL;
