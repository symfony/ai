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

$result = $platform->invoke('cartesia/sonic', new Text('Hello world, this is Symfony AI speaking.'), [
    'voice' => 'friendly sidekick',
    'response_format' => 'mp3',
]);

echo $result->asBinary().\PHP_EOL;
