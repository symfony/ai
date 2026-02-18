<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('VENICE_API_KEY'), httpClient: http_client());

$result = $platform->invoke('tts-kokoro', new Text('Hello world'), [
    'voice' => 'am_liam',
]);

echo $result->asBinary().\PHP_EOL;
