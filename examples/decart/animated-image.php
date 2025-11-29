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
use Symfony\AI\Platform\Message\Content\Image;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    apiKey: env('DECART_API_KEY'),
    httpClient: http_client(),
);

$result = $platform->invoke('lucy-pro-i2v', Image::fromFile(dirname(__DIR__, 2).'/fixtures/accordion.jpg'), [
    'prompt' => 'Make the man move from right to left while playing accordion',
]);

echo $result->asBinary().\PHP_EOL;
