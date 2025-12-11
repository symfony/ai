<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENROUTER_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('Check the web for details that the user ask.'),
    Message::ofUser('Who win the Formula1 GP in Brasilia at 2025-11-09?'),
);

// ":online" add web search tooling to the OpenRouter model
$result = $platform->invoke('google/gemini-2.5-flash-lite:online', $messages);

// Example result:
// Lando Norris won the Formula 1 Grand Prix in Brasilia on November 9, 2025. This victory significantly extended his lead
// in the Drivers' Championship [planetf1.com]. Max Verstappen finished third after starting from the pit lane [motorsportweek.com].

echo $result->asText().\PHP_EOL;
