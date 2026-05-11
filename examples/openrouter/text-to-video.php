<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\Factory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENROUTER_KEY'), http_client());

// Note: The rendering takes about 1-2 minutes and cost about $ 0.2
// Suggest: Execute it with verbosity (-vv) to get information of the polling requests

$result = $platform->invoke('google/veo-3.1-lite', new Text('A serene ocean with dolphins jumping at sunset'), [
    'duration' => 4,
    'resolution' => '720p',
    'aspect_ratio' => '16:9',
]);

$targetFile = sys_get_temp_dir().'/openrouter-video-'.uniqid('', true).'.mp4';
$result->asFile($targetFile);

echo 'Video saved to: '.$targetFile.\PHP_EOL;
