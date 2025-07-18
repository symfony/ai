<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAI\DallE;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAI\DallE\Base64Image;
use Symfony\AI\Platform\Bridge\OpenAI\DallE\ImageResult;
use Symfony\AI\Platform\Bridge\OpenAI\DallE\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenAI\DallE\UrlImage;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

#[CoversClass(ResultConverter::class)]
#[UsesClass(UrlImage::class)]
#[UsesClass(Base64Image::class)]
#[UsesClass(ImageResult::class)]
#[Small]
final class ResponseConverterTest extends TestCase
{
    #[Test]
    public function itIsConvertingTheResponse(): void
    {
        $httpResponse = self::createStub(HttpResponse::class);
        $httpResponse->method('toArray')->willReturn([
            'data' => [
                ['url' => 'https://example.com/image.jpg'],
            ],
        ]);

        $resultConverter = new ResultConverter();
        $result = $resultConverter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);

        self::assertCount(1, $result->getContent());
        self::assertInstanceOf(UrlImage::class, $result->getContent()[0]);
        self::assertSame('https://example.com/image.jpg', $result->getContent()[0]->url);
    }

    #[Test]
    public function itIsConvertingTheResponseWithRevisedPrompt(): void
    {
        $emptyPixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $httpResponse = self::createStub(HttpResponse::class);
        $httpResponse->method('toArray')->willReturn([
            'data' => [
                ['b64_json' => $emptyPixel, 'revised_prompt' => 'revised prompt'],
            ],
        ]);

        $resultConverter = new ResultConverter();
        $result = $resultConverter->convert(new RawHttpResult($httpResponse), ['response_format' => 'b64_json']);

        self::assertInstanceOf(ImageResult::class, $result);
        self::assertCount(1, $result->getContent());
        self::assertInstanceOf(Base64Image::class, $result->getContent()[0]);
        self::assertSame($emptyPixel, $result->getContent()[0]->encodedImage);
        self::assertSame('revised prompt', $result->revisedPrompt);
    }
}
