<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENROUTER_KEY'), http_client());

# Note: The rendering takes several minutes (!!!) and cost about $ 1.6
# Suggest: Execute it with verbosity (-vv) to get information of the polling requests

$result = $platform->invoke('google/veo-3.1', new Text('A serene ocean with dolphins jumping at sunset'), [
    'duration' => 4,
    'resolution' => '1080p',
    'aspect_ratio' => '16:9',
]);

$targetFile = sys_get_temp_dir().'/openrouter-video-'.uniqid('', true).'.mp4';
$result->asFile($targetFile);

echo 'Video saved to: '.$targetFile.\PHP_EOL;
