<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\DallE;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\Base64Image;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\ImageResult;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\UrlImage;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ResultConverterTest extends TestCase
{
    public function testItIsConvertingTheResponse()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('toArray')->willReturn([
            'data' => [
                ['url' => 'https://example.com/image.jpg'],
            ],
        ]);

        $resultConverter = new ResultConverter();
        $result = $resultConverter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);

        $this->assertCount(1, $result->getContent());
        $this->assertInstanceOf(UrlImage::class, $result->getContent()[0]);
        $this->assertSame('https://example.com/image.jpg', $result->getContent()[0]->url);
    }

    public function testItIsConvertingTheResponseWithRevisedPrompt()
    {
        $emptyPixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('toArray')->willReturn([
            'data' => [
                ['b64_json' => $emptyPixel, 'revised_prompt' => 'revised prompt'],
            ],
        ]);

        $resultConverter = new ResultConverter();
        $result = $resultConverter->convert(new RawHttpResult($httpResponse), ['response_format' => 'b64_json']);

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertInstanceOf(Base64Image::class, $result->getContent()[0]);
        $this->assertSame($emptyPixel, $result->getContent()[0]->encodedImage);
        $this->assertSame('revised prompt', $result->revisedPrompt);
    }
}
