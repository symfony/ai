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
use Symfony\AI\Platform\Bridge\LmStudio\Completions;
use Symfony\AI\Platform\Bridge\LmStudio\PlatformFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('LMSTUDIO_HOST_URL'), http_client());
$model = Completions::create(
    name: 'gemma-3-4b-it-qat',
    capabilities: [...Completions::DEFAULT_CAPABILITIES, Capability::INPUT_IMAGE]
);

$agent = new Agent($platform, $model, logger: logger());
$messages = new MessageBag(
    Message::forSystem('You are an image analyzer bot that helps identify the content of images.'),
    Message::ofUser(
        'Describe the image as a comedian would do it.',
        Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'),
    ),
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
