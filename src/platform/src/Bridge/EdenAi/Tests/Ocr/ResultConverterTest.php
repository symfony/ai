<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\Ocr;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsOcrModelOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Ocr('ocr/ocr/google')));
        $this->assertFalse($converter->supports(new DocumentParser('ocr/resume_parser/affinda')));
    }

    public function testItConvertsResponseToOcrResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'success',
            'provider' => 'google',
            'cost' => '0.01',
            'output' => [
                'text' => 'Hello world',
                'bounding_boxes' => [
                    [
                        'text' => 'Hello',
                        'left' => 0.1,
                        'top' => 0.2,
                        'width' => 0.3,
                        'height' => 0.4,
                    ],
                    [
                        'text' => 'world',
                    ],
                ],
            ],
            'error' => null,
            'original_response' => null,
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $ocr = $result->getContent();

        $this->assertInstanceOf(OcrResult::class, $ocr);
        $this->assertSame('Hello world', $ocr->getText());
        $this->assertCount(2, $ocr->getBoundingBoxes());
        $this->assertSame('Hello', $ocr->getBoundingBoxes()[0]->getText());
        $this->assertSame(0.1, $ocr->getBoundingBoxes()[0]->getLeft());
        $this->assertSame(0.2, $ocr->getBoundingBoxes()[0]->getTop());
        $this->assertSame(0.3, $ocr->getBoundingBoxes()[0]->getWidth());
        $this->assertSame(0.4, $ocr->getBoundingBoxes()[0]->getHeight());
        $this->assertNull($ocr->getBoundingBoxes()[1]->getLeft());
        $this->assertSame('google', $ocr->getProvider());
        $this->assertSame(0.01, $ocr->getCost());
        $this->assertNull($ocr->getOriginalResponse());
    }

    public function testItConvertsResponseWithoutBoundingBoxes()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse(['status' => 'success', 'output' => ['text' => 'Just text']]);

        $result = $converter->convert(new RawHttpResult($httpResponse));
        $ocr = $result->getContent();

        $this->assertInstanceOf(OcrResult::class, $ocr);
        $this->assertSame('Just text', $ocr->getText());
        $this->assertSame([], $ocr->getBoundingBoxes());
    }

    public function testItThrowsWhenProviderFails()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Provider mistral doesn\'t support file type: application/pdf for this feature.'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Provider mistral doesn\'t support file type: application/pdf for this feature."');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testItThrowsWhenTextIsMissing()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createResponse(['status' => 'success', 'output' => ['bounding_boxes' => []]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain text.');

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
