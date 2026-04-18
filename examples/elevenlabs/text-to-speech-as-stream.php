<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ElevenLabs\Factory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(apiKey: env('ELEVEN_LABS_API_KEY'), httpClient: http_client());

$result = $platform->invoke('eleven_multilingual_v2', new Text('The first move is what sets everything in motion.'), [
    'voice' => 'pqHfZKP75CvOlQylNhV4', // Bill
    'stream' => true,
]);

$content = '';

foreach ($result->asStream() as $chunk) {
    if ($chunk instanceof BinaryDelta) {
        echo $chunk->getData();
    }
}

echo \PHP_EOL;
