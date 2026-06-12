<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Message\Content\Document;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('MISTRAL_API_KEY'), httpClient: http_client());

$result = $platform->invoke('mistral-ocr-latest', Document::fromFile(dirname(__DIR__, 2).'/fixtures/document.pdf'));

$ocr = $result->asObject();
assert($ocr instanceof OcrResult);

echo $ocr->getMarkdown().\PHP_EOL.\PHP_EOL;
echo 'Pages processed: '.count($ocr->getPages()).\PHP_EOL;
