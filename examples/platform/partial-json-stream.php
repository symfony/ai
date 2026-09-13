<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

/*
 * Streams structured JSON output from the model and re-parses the running
 * buffer after every delta. The model is constrained with a JSON schema
 * (structured output) but the response is consumed as a plain array instead of
 * being mapped onto a typed object. Each iteration of asPartialJsonStream()
 * yields the densest valid structure recoverable so far, so the UI can render
 * it progressively without waiting for the full payload.
 */

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a chef that replies with a recipe.'),
    Message::ofUser('Give me a simple recipe for a classic Margherita pizza.'),
);

$deferred = $platform->invoke('gpt-4o-mini', $messages, [
    'stream' => true,
    // A raw JSON schema (no PHP class) constrains the streamed output. The model
    // client forwards it as the OpenAI structured-output format, so every delta
    // is a fragment of a schema-valid recipe object.
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'recipe',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'The name of the dish.'],
                    'ingredients' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The ingredients needed for the recipe.'],
                    'steps' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The ordered preparation steps.'],
                ],
                'required' => ['name', 'ingredients', 'steps'],
                'additionalProperties' => false,
            ],
        ],
    ],
]);

// Each snapshot is the largest valid JSON value recovered so far: the name
// appears first, then ingredients trickle in one by one, then the steps. On an
// interactive terminal we re-render the whole recipe card on every snapshot so it
// shows up filling live; when the output is captured - by the example runner or a
// pipe - the clear-screen escape sequences would garble it, so the card is printed
// once at the end instead.
$live = is_interactive();
$snapshots = 0;
$latest = null;

/**
 * @param array{name?: string|null, ingredients?: list<string>, steps?: list<string>} $recipe
 */
$render = static function (array $recipe): void {
    echo '🍕 '.($recipe['name'] ?? '…')."\n\n";

    echo "Ingredients\n-----------\n";
    foreach ($recipe['ingredients'] ?? [] as $ingredient) {
        echo '  • '.$ingredient."\n";
    }

    echo "\nSteps\n-----\n";
    foreach ($recipe['steps'] ?? [] as $index => $step) {
        echo sprintf("  %d. %s\n", $index + 1, $step);
    }
};

foreach ($deferred->asPartialJsonStream() as $snapshot) {
    if (!is_array($snapshot)) {
        continue;
    }

    ++$snapshots;
    $latest = $snapshot;

    if (!$live) {
        continue;
    }

    echo "\033[2J\033[H"; // clear screen and move the cursor to the top-left
    $render($snapshot);
}

if (!$live && null !== $latest) {
    echo sprintf("Recovered %d partial snapshots, the last one being:\n\n", $snapshots);
    $render($latest);
}
