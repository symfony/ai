<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\DocumentParser;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser\Result\DocumentParsingResult;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser\ResultConverter;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsDocumentParserModelOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new DocumentParser('ocr/financial_parser/affinda')));
        $this->assertFalse($converter->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItConvertsResponseToDocumentParsingResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'success',
            'provider' => 'affinda',
            'cost' => '0.07',
            'output' => [
                'extracted_data' => [
                    [
                        'merchant_information' => ['merchant_name' => 'ACME Corp'],
                        'payment_information' => ['total' => '42.00'],
                    ],
                ],
            ],
            'error' => null,
            'original_response' => null,
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $parsing = $result->getContent();

        $this->assertInstanceOf(DocumentParsingResult::class, $parsing);
        $this->assertSame('ACME Corp', $parsing->getExtractedData()[0]['merchant_information']['merchant_name']);
        $this->assertSame('affinda', $parsing->getProvider());
        $this->assertSame(0.07, $parsing->getCost());
        $this->assertNull($parsing->getOriginalResponse());
    }

    public function testItThrowsWhenProviderFails()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Insufficient AI credit.'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Insufficient AI credit."');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testItThrowsWhenExtractedDataIsMissing()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse(['status' => 'success', 'provider' => 'affinda', 'output' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain extracted_data.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data): ResponseInterface
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);
        $httpResponse->method('toArray')->willReturn($data);

        return $httpResponse;
    }
}
