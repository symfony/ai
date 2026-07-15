<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$messages = new MessageBag(
    Message::forSystem('You are an encyclopedia.'),
    Message::ofUser('Explain the history of the Roman Empire in great detail.'),
);

$platforms = [
    'openai' => [OpenAiFactory::createPlatform(env('OPENAI_API_KEY'), http_client()), 'gpt-4o-mini', ['max_output_tokens' => 16]],
    'anthropic' => [AnthropicFactory::createPlatform(env('ANTHROPIC_API_KEY'), http_client()), 'claude-sonnet-4-5-20250929', ['max_tokens' => 16]],
];

foreach ($platforms as $name => [$platform, $model, $options]) {
    output()->writeln(sprintf('<info>%s</info>', $name));

    $result = $platform->invoke($model, $messages, $options);
    $result->asText();

    $finishReason = $result->getMetadata()->get('finish_reason');

    print_finish_reason($finishReason);

    if ($finishReason->is(FinishReasonCase::LENGTH)) {
        output()->writeln('<comment>The answer above is cut off -- raise the output token limit to see the rest.</comment>');
    }
}

output()->writeln('<info>anthropic (streamed)</info>');

[$platform, $model] = $platforms['anthropic'];
$result = $platform->invoke($model, new MessageBag(Message::ofUser('Reply with a single short sentence.')), ['stream' => true, 'max_tokens' => 100]);

foreach ($result->asTextStream() as $delta) {
    echo $delta;
}
echo \PHP_EOL;

print_finish_reason($result->getMetadata()->get('finish_reason'));
