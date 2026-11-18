<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Bridge\Generic\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformOptions;

require_once dirname(__DIR__).'/bootstrap.php';

$modelCatalog = new ModelCatalog([
    'mistral-small-latest' => [
        'class' => CompletionsModel::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::INPUT_IMAGE,
            Capability::TOOL_CALLING,
        ],
    ],
]);

$platform = Factory::createPlatform(
    'http://127.0.0.1:4000',
    env('LITELLM_API_KEY'),
    http_client(),
    $modelCatalog,
);

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $platform->invoke('mistral-small-latest', $messages, [
    PlatformOptions::PROVIDER_OPTIONS => [
        'max_tokens' => 500, // specific options just for this call
    ],
]);

echo $result->asText().\PHP_EOL;

print_token_usage($result->getMetadata()->get('token_usage'));

echo \PHP_EOL;
