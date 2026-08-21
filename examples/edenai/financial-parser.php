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

$result = $platform->invoke('ocr/financial_parser/openai/gpt-4o', new DocumentUrl('https://slicedinvoices.com/pdf/wordpress-pdf-invoice-plugin-sample.pdf'), [
    'language' => 'en',
    'document_type' => 'invoice',
]);

$parsing = $result->asObject();
assert($parsing instanceof DocumentParsingResult);

$page = $parsing->getExtractedData()[0] ?? [];

echo 'Merchant: '.($page['merchant_information']['name'] ?? 'n/a').\PHP_EOL;
echo 'Total: '.($page['payment_information']['total'] ?? 'n/a').\PHP_EOL;
echo 'Invoice date: '.($page['financial_document_information']['invoice_date'] ?? 'n/a').\PHP_EOL;
