<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ovh\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OVH_AI_SECRET_KEY'), http_client());

$result = $platform->invoke('stable-diffusion-xl-base-v10', 'A cat sitting on a kitchen table, photorealistic');

$result->asFile(__DIR__.'/image.png');

echo 'Image saved to image.png'.\PHP_EOL;
