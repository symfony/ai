<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Fireworks\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('FIREWORKS_API_KEY'), http_client());

$result = $platform->invoke('accounts/fireworks/models/flux-1-schnell-fp8', 'A cat in a space suit floating in front of the moon', [
    'aspect_ratio' => '16:9',
]);

$path = sys_get_temp_dir().'/fireworks-image.png';
$result->asFile($path);

output()->writeln(sprintf('Image saved to %s', $path));
