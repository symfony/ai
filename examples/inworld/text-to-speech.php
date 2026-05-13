<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Inworld\Factory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: env('INWORLD_API_KEY'),
    httpClient: http_client(),
);

$result = $platform->invoke('inworld-tts-2', new Text('The first move is what sets everything in motion.'), [
    'voice' => 'Dennis',
]);

echo $result->asBinary().\PHP_EOL;
