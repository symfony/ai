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
use Symfony\AI\Platform\Message\Content\Document;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('EDENAI_API_KEY'), http_client());

// The local PDF is transparently uploaded through Eden AI's /v3/upload endpoint
$result = $platform->invoke('ocr/resume_parser/openai/gpt-4o', Document::fromFile(dirname(__DIR__, 2).'/fixtures/resume.pdf'));

$parsing = $result->asObject();
assert($parsing instanceof DocumentParsingResult);

echo json_encode($parsing->getExtractedData(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE).\PHP_EOL;
