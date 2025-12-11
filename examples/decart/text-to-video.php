<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Decart\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    apiKey: env('DECART_API_KEY'),
    httpClient: http_client(),
);

$result = $platform->invoke('lucy-pro-t2v', new Text('A serene ocean with dolphins jumping at sunset'));

echo $result->asBinary();
