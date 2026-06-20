<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Ocr;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\Ocr;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsOcrModelOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Ocr('mistral-ocr-latest')));
        $this->assertFalse($converter->supports(new Mistral('mistral-large-latest')));
    }

    public function testItConvertsPagesIntoStructuredResult()
    {
        $response = new JsonMockResponse([
            'model' => 'mistral-ocr-latest',
            'pages' => [
                [
                    'index' => 0,
                    'markdown' => "# Title\n\nFirst page.",
                    'dimensions' => ['width' => 2480, 'height' => 3508, 'dpi' => 200],
                    'images' => [
                        [
                            'id' => 'img-0',
                            'top_left_x' => 10,
                            'top_left_y' => 20,
                            'bottom_right_x' => 110,
                            'bottom_right_y' => 220,
                            'image_base64' => 'data:image/png;base64,abc',
                        ],
                    ],
                ],
                [
                    'index' => 1,
                    'markdown' => 'Second page.',
                ],
            ],
            'document_annotation' => '{"language":"en"}',
            'usage_info' => ['pages_processed' => 2, 'doc_size_bytes' => 12345],
        ]);

        $result = (new ResultConverter())->convert(new RawHttpResult($this->responseOf($response)));

        $this->assertInstanceOf(ObjectResult::class, $result);

        $ocr = $result->getContent();
        \assert($ocr instanceof OcrResult);

        $this->assertSame('mistral-ocr-latest', $ocr->getModel());
        $this->assertCount(2, $ocr->getPages());
        $this->assertSame("# Title\n\nFirst page.\n\nSecond page.", $ocr->getMarkdown());
        $this->assertSame('{"language":"en"}', $ocr->getDocumentAnnotation());
        $this->assertSame(['pages_processed' => 2, 'doc_size_bytes' => 12345], $ocr->getUsageInfo());

        $firstPage = $ocr->getPages()[0];
        $this->assertSame(0, $firstPage->getIndex());
        $this->assertSame(['width' => 2480, 'height' => 3508, 'dpi' => 200], $firstPage->getDimensions());
        $this->assertCount(1, $firstPage->getImages());

        $image = $firstPage->getImages()[0];
        $this->assertSame('img-0', $image->getId());
        $this->assertSame(10, $image->getTopLeftX());
        $this->assertSame(220, $image->getBottomRightY());
        $this->assertSame('data:image/png;base64,abc', $image->getImageBase64());
    }

    public function testItThrowsWhenPagesAreMissing()
    {
        $result = new RawHttpResult($this->responseOf(new JsonMockResponse(['model' => 'mistral-ocr-latest'])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain pages.');

        (new ResultConverter())->convert($result);
    }

    private function responseOf(JsonMockResponse $response): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        $client = new MockHttpClient($response);

        return $client->request('POST', 'https://api.mistral.ai/v1/ocr');
    }
}
