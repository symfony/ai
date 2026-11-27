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

require_once dirname(__DIR__).'/bootstrap.php';

$model = env('GIGACHAT_AI_STUDIO_MODEL');
$platform = Factory::createPlatform(
    'https://api.aistudio.giga.chat',
    env('GIGACHAT_AI_STUDIO_API_KEY'),
    http_client(),
    new ModelCatalog([
        $model => [
            'class' => CompletionsModel::class,
            'capabilities' => [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT],
        ],
    ]),
    supportsEmbeddings: false,
);

$messages = new MessageBag(Message::ofUser('What is the Symfony framework?'));
$result = $platform->invoke($model, $messages);

echo $result->asText().\PHP_EOL;
