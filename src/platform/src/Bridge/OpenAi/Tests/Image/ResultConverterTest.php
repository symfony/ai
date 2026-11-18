<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Image;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Image\ResultConverter;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ResultConverterTest extends TestCase
{
    private const EMPTY_PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function testItConvertsASingleImageToABinaryResult()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('toArray')->willReturn([
            'data' => [
                ['b64_json' => self::EMPTY_PIXEL],
            ],
        ]);

        $result = (new ResultConverter())->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/png', $result->getMimeType());
        $this->assertSame(self::EMPTY_PIXEL, $result->toBase64());
    }

    public function testItConvertsMultipleImagesToAMultiPartResult()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('toArray')->willReturn([
            'data' => [
                ['b64_json' => self::EMPTY_PIXEL],
                ['b64_json' => self::EMPTY_PIXEL],
            ],
        ]);

        $result = (new ResultConverter())->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertContainsOnlyInstancesOf(BinaryResult::class, $result->getContent());
    }

    public function testItUsesTheRequestedOutputFormatAsMimeType()
    {
        $httpResponse = $this->createStub(HttpResponse::class);
        $httpResponse->method('toArray')->willReturn([
            'data' => [
                ['b64_json' => self::EMPTY_PIXEL],
            ],
        ]);

        $result = (new ResultConverter())->convert(new RawHttpResult($httpResponse), [PlatformOptions::PROVIDER_OPTIONS => ['output_format' => 'webp']]);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/webp', $result->getMimeType());
    }
}
