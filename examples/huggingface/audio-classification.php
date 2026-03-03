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

require_once dirname(__DIR__).'/bootstrap.php';

// This model currently has no inference provider available on HuggingFace.
echo 'Skipped: No inference provider currently available for audio-classification task.'.\PHP_EOL;

return;

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client()); // @phpstan-ignore deadCode.unreachable
$audio = Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3');

$result = $platform->invoke('MIT/ast-finetuned-audioset-10-10-0.4593', $audio, [
    'task' => Task::AUDIO_CLASSIFICATION,
]);

dump($result->asObject());
