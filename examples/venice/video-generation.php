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
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('VENICE_API_KEY'), httpClient: http_client());

$result = $platform->invoke('seedance-1-5-pro-text-to-video', new Text('A serene ocean with dolphins jumping at sunset'));

$result->asFile(__DIR__.'/ocean.mp4');
