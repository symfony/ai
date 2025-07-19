<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());
$model = new Model('MIT/ast-finetuned-audioset-10-10-0.4593');
$audio = Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3');

$response = $platform->request($model, $audio, [
    'task' => Task::AUDIO_CLASSIFICATION,
]);

dump($response->asObject());
