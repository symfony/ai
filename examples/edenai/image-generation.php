<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\EdenAi\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

$result = $platform->invoke('image/generation/stabilityai', 'A minimalist illustration of an elephant coding on a laptop');

$result->asFile(__DIR__.'/image-generation.png');

echo 'Image saved to image-generation.png'.\PHP_EOL;
