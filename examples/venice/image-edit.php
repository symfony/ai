<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Venice\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('VENICE_API_KEY'), httpClient: http_client());

$result = $platform->invoke('firered-image-edit', [
    'image' => 'https://venice.ai/static/example-input.png',
    'prompt' => 'Convert it to a sepia tone with a vintage feel',
]);

$result->asFile(__DIR__.'/edited.png');
