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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\Clock;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\TokenOutputProcessor;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextChunk;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$clock = new Clock();
$openMeteo = new Symfony\AI\Agent\Toolbox\Tool\OpenMeteo(http_client());
$toolbox = new Toolbox([$clock, $openMeteo], logger: logger());
$processor = new AgentProcessor($toolbox);

$tokenOutputProcessor = new TokenOutputProcessor();

$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor]);
$messages = new MessageBag(Message::ofUser(<<<TXT
    Tell me the time and the weather in Dublin.
    TXT));
$result = $agent->call($messages, [
    'stream' => true, // enable streaming of response text
    'stream_options' => [
        'include_usage' => true, // include usage in the response
    ],
]);

// Output text chunks
/** @var TextChunk $textChunk */
foreach ($result->getContent() as $textChunk) {
    // $textChunk implement \Stringable
    echo $textChunk->getContent();

    // Chunk also contain metadata
    // $textChunk->getMetadata()->get('id'); // Stream id
}

// Output token usage statistics for each call
foreach ($result->getMetadata()->get('calls', []) as $call) {
    echo \PHP_EOL.sprintf(
        '%s: %d tokens - Finish reason: [%s]',
        $call['id'],
        $call['usage']['total_tokens'],
        $call['finish_reason']
    );
}

echo \PHP_EOL;
