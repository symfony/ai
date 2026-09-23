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
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// The Responses API does not support audio input yet, so this example uses the Chat Completions API
$modelCatalog = new ModelCatalog([
    'gpt-audio' => [
        'class' => CompletionsModel::class,
        'capabilities' => [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
        ],
    ],
]);

$platform = Factory::createPlatform('https://api.openai.com', env('OPENAI_API_KEY'), http_client(), $modelCatalog, supportsEmbeddings: false);

$messages = new MessageBag(
    Message::ofUser(
        'What is this recording about?',
        Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'),
    ),
);
$result = $platform->invoke('gpt-audio', $messages);

echo $result->asText().\PHP_EOL;
