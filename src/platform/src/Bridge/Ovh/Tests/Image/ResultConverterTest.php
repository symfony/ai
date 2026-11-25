<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\Image;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\Image;
use Symfony\AI\Platform\Bridge\Ovh\Image\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsImageModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Image('stable-diffusion-xl-base-v10')));
        $this->assertFalse($converter->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItConvertsASingleImageToBinaryResult()
    {
        $result = (new ResultConverter())->convert($this->createResult(['created' => 1, 'data' => [['b64_json' => base64_encode('image-bytes')]]]));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image-bytes', $result->getContent());
        $this->assertSame('image/png', $result->getMimeType());
    }

    public function testItUsesTheOutputFormatOfTheResponse()
    {
        $result = (new ResultConverter())->convert($this->createResult(['created' => 1, 'output_format' => 'jpeg', 'data' => [['b64_json' => base64_encode('image-bytes')]]]));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/jpeg', $result->getMimeType());
    }

    public function testItConvertsSeveralImagesToMultiPartResult()
    {
        $result = (new ResultConverter())->convert($this->createResult(['created' => 1, 'data' => [
            ['b64_json' => base64_encode('first')],
            ['b64_json' => base64_encode('second')],
        ]]));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertCount(2, $result->getContent());
    }

    public function testItThrowsExceptionWhenNoImageIsGenerated()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No image generated.');

        (new ResultConverter())->convert($this->createResult(['created' => 1, 'data' => []]));
    }

    public function testItThrowsExceptionWhenTheImageIsNotBase64Encoded()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The image is not base64-encoded');

        (new ResultConverter())->convert($this->createResult(['created' => 1, 'data' => [['url' => 'https://example.com/image.png']]]));
    }

    public function testItThrowsExceptionOnServerError()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        (new ResultConverter())->convert($this->createResult([], 500));
    }

    public function testItHasNoTokenUsageExtractor()
    {
        $this->assertNull((new ResultConverter())->getTokenUsageExtractor());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createResult(array $body, int $status = 200): RawHttpResult
    {
        return new RawHttpResult((new MockHttpClient(new MockResponse(json_encode($body), ['http_code' => $status])))->request('POST', 'https://example.com/'));
    }
}
