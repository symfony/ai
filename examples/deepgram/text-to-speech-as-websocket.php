<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Deepgram\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(apiKey: env('DEEPGRAM_API_KEY'), useWebsockets: true, httpClient: http_client());

$result = $platform->invoke('aura-2-thalia-en', new Text('Hello world'));

echo $result->asBinary().\PHP_EOL;
