<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Decart\Factory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: env('DECART_API_KEY'),
    httpClient: http_client(),
);

$result = $platform->invoke('lucy-pro-t2i', new Text('A cat on a kitchen table'));

echo $result->asBinary().\PHP_EOL;
