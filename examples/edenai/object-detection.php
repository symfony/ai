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
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis\Result\ImageAnalysisResult;
use Symfony\AI\Platform\Message\Content\ImageUrl;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

$result = $platform->invoke('image/object_detection/google', new ImageUrl('https://raw.githubusercontent.com/symfony/ai/main/fixtures/accordion.jpg'));

$analysis = $result->asObject();
assert($analysis instanceof ImageAnalysisResult);

foreach ($analysis->getItems() as $item) {
    echo sprintf('%s (%.0f%%)', $item['label'], 100 * $item['confidence']).\PHP_EOL;
}
