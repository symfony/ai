<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser\Result\DocumentParsingResult;
use Symfony\AI\Platform\Bridge\EdenAi\Factory;
use Symfony\AI\Platform\Message\Content\DocumentUrl;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

$result = $platform->invoke('ocr/resume_parser/openai/gpt-4o', new DocumentUrl('https://upload.wikimedia.org/wikipedia/commons/9/96/ROC_civil_services_resume_%28general%29_filling_example_20130506.pdf'));

$parsing = $result->asObject();
assert($parsing instanceof DocumentParsingResult);

echo json_encode($parsing->getExtractedData(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE).\PHP_EOL;
