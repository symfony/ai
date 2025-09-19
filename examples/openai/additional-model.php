<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$modelCatalog = new ModelCatalog([
    'gpt-4o-mini-transcribe' => [
        'class' => Gpt::class,
        'capabilities' => [
            Capability::INPUT_AUDIO,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_TEXT,
        ],
    ],
]);

// Create platform with the custom model catalog
$platform = PlatformFactory::create(
    env('OPENAI_API_KEY'),
    http_client(),
    catalog: $modelCatalog
);

// Use the transcription model
$transcribeModel = $modelCatalog->getModel('gpt-4o-mini-transcribe');

$messages = new MessageBag(
    Message::ofUser(
        'Please transcribe this audio file.',
        Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3'),
    ),
);

$result = $platform->invoke($transcribeModel, $messages);

echo $result->getResult()->getContent()."\n\n";
