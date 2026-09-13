<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$copywriter = new Agent($platform, 'gpt-5-mini', name: 'copywriter');
$artDirector = new Agent($platform, 'gpt-5-mini', name: 'art-director');

// Both agents work on the same conversation, but each keeps its own view of it: what the other
// one says arrives as a user message, what it said itself stays an assistant message.
$copywriterMessages = new MessageBag(
    Message::forSystem(<<<PROMPT
        You are a copywriter with ten years of experience and are known for brevity and a dry humor.
        You are laser focused on the goal at hand: refining a single slogan of at most eight words
        until it is the best it can be. Consider the art director's suggestions when refining it, and
        reply with the slogan only - no explanation, no alternatives, no chit chat.
        PROMPT),
    Message::ofUser('Write a slogan for this concept: maps made out of egg cartons.'),
);

$artDirectorMessages = new MessageBag(
    Message::forSystem(<<<PROMPT
        You are an art director who has opinions about copywriting born of a love for David Ogilvy.
        Decide whether the slogan you are given is acceptable to print: short, memorable and on
        concept is enough, do not chase perfection. If it is acceptable, reply with exactly
        "APPROVED". If it is not, reply with a single sentence of concrete advice on how to refine
        it - never write the copy yourself.
        PROMPT),
);

$maxRounds = 4;
$approved = false;
$slogan = '';

for ($round = 1; $round <= $maxRounds; ++$round) {
    $slogan = $copywriter->call($copywriterMessages)->asText();
    $copywriterMessages = $copywriterMessages->with(Message::ofAssistant($slogan));
    $artDirectorMessages = $artDirectorMessages->with(Message::ofUser($slogan));

    output()->writeln(sprintf('<info>Round %d, copywriter:</info> %s', $round, $slogan));

    $verdict = $artDirector->call($artDirectorMessages)->asText();
    $artDirectorMessages = $artDirectorMessages->with(Message::ofAssistant($verdict));

    // The reviewer decides when the loop is done, the round cap only keeps it from running forever
    $approved = str_contains(strtoupper($verdict), 'APPROVED');
    if ($approved) {
        break;
    }

    output()->writeln(sprintf('<comment>Round %d, art director:</comment> %s', $round, $verdict));

    $copywriterMessages = $copywriterMessages->with(Message::ofUser($verdict));
}

output()->writeln('');

if ($approved) {
    output()->writeln(sprintf('<info>Approved in round %d:</info> %s', $round, $slogan));
} else {
    output()->writeln(sprintf('<comment>Still not approved after %d rounds, last version:</comment> %s', $maxRounds, $slogan));
}
