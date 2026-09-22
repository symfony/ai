<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\EdenAi\Factory;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Message\Content\ImageUrl;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

$result = $platform->invoke('ocr/ocr/google', new ImageUrl('https://raw.githubusercontent.com/symfony/ai/main/fixtures/image.jpg'), [
    'language' => 'en',
]);

$ocr = $result->asObject();
assert($ocr instanceof OcrResult);

echo $ocr->getText().\PHP_EOL;
echo 'Bounding boxes: '.count($ocr->getBoundingBoxes()).\PHP_EOL;
