
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result\Transcript;
use Symfony\AI\Platform\Message\Content\Audio;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$file = Audio::fromFile(dirname(__DIR__, 2).'/fixtures/audio.mp3');

$result = $platform->invoke('whisper-1', $file, [
    'verbose' => true,
]);

$transcript = $result->asObject();

assert($transcript instanceof Transcript);

echo 'Full Transcript: '.$transcript->getText().\PHP_EOL.\PHP_EOL;

echo 'Language: '.$transcript->getLanguage().\PHP_EOL;
echo 'Duration: '.$transcript->getDuration().' seconds'.\PHP_EOL;
echo 'Segments:'.\PHP_EOL;
foreach ($transcript->getSegments() as $segment) {
    echo sprintf(
        ' [%0.2f - %0.2f] %s'.\PHP_EOL,
        $segment->getStart(),
        $segment->getEnd(),
        $segment->getText(),
    );
}
