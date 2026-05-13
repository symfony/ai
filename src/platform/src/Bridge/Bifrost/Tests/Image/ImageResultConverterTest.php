<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests\Image;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Image\Base64Image;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageModel;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageResult;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageResultConverter;
use Symfony\AI\Platform\Bridge\Bifrost\Image\UrlImage;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageResultConverterTest extends TestCase
{
    public function testItSupportsImageModelOnly()
    {
        $converter = new ImageResultConverter();

        $this->assertTrue($converter->supports(new ImageModel('openai/dall-e-3')));
        $this->assertFalse($converter->supports(new Model('test-model')));
    }

    public function testItConvertsUrlImages()
    {
        $rawResult = $this->createRawResult([
            'data' => [
                ['url' => 'https://example.com/image1.png', 'revised_prompt' => 'A revised prompt'],
                ['url' => 'https://example.com/image2.png'],
            ],
        ]);

        $result = (new ImageResultConverter())->convert($rawResult);

        $this->assertInstanceOf(ImageResult::class, $result);
        $this->assertSame('A revised prompt', $result->getRevisedPrompt());

        $images = $result->getContent();
        $this->assertIsArray($images);
        $this->assertCount(2, $images);
        $this->assertInstanceOf(UrlImage::class, $images[0]);
        $this->assertSame('https://example.com/image1.png', $images[0]->url);
        $this->assertInstanceOf(UrlImage::class, $images[1]);
    }

    public function testItConvertsBase64Images()
    {
        $rawResult = $this->createRawResult([
            'data' => [
                ['b64_json' => 'aGVsbG8='],
            ],
        ]);

        $result = (new ImageResultConverter())->convert($rawResult);
        $this->assertInstanceOf(ImageResult::class, $result);

        $images = $result->getContent();
        $this->assertIsArray($images);
        $this->assertCount(1, $images);
        $this->assertInstanceOf(Base64Image::class, $images[0]);
        $this->assertSame('aGVsbG8=', $images[0]->encodedImage);
    }

    public function testItThrowsWhenNoImageReturned()
    {
        $rawResult = $this->createRawResult(['data' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No image generated.');

        (new ImageResultConverter())->convert($rawResult);
    }

    public function testItThrowsWhenImageHasNoUrlNorBase64()
    {
        $rawResult = $this->createRawResult([
            'data' => [
                ['something' => 'else'],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Each generated image must expose either a "url" or a "b64_json" field.');

        (new ImageResultConverter())->convert($rawResult);
    }

    public function testItThrowsAuthenticationExceptionOn401()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('toArray')->willReturn(['error' => ['message' => 'Invalid API key.']]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key.');

        (new ImageResultConverter())->convert(new RawHttpResult($response));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createRawResult(array $data): RawHttpResult
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn($data);

        return new RawHttpResult($response);
    }
}
