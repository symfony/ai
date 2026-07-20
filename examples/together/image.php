<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Together\Factory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(apiKey: env('TOGETHER_API_KEY'), httpClient: http_client());

$result = $platform->invoke('black-forest-labs/FLUX.1-schnell', new Text('A cat sitting on a kitchen table, photorealistic'));

echo $result->asBinary().\PHP_EOL;
