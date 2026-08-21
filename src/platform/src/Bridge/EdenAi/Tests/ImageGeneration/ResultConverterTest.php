<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\ImageGeneration;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration\ResultConverter;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverterTest extends TestCase
{
    public function testItSupportsImageGenerationModelOnly()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new ImageGeneration('image/generation/openai')));
        $this->assertFalse($converter->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItConvertsGeneratedImageToBinaryResult()
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'provider' => 'openai',
            'cost' => '0.042000000',
            'output' => [
                'items' => [
                    [
                        'image' => base64_encode('png-binary-content'),
                        'image_resource_url' => 'https://example.com/generated.png',
                    ],
                ],
            ],
        ]));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('png-binary-content', $result->getContent());
        $this->assertSame('image/png', $result->getMimeType());
    }

    /**
     * "num_images" accepts up to 10 and every generated image is billed, so none may be
     * dropped.
     */
    public function testItConvertsEveryGeneratedImageToAMultiPartResult()
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => [
                'items' => [
                    ['image' => base64_encode('first'), 'image_resource_url' => 'https://example.com/1.png'],
                    ['image' => base64_encode('second'), 'image_resource_url' => 'https://example.com/2.png'],
                    ['image' => base64_encode('third'), 'image_resource_url' => 'https://example.com/3.png'],
                ],
            ],
        ]));

        $this->assertInstanceOf(MultiPartResult::class, $result);

        $images = $result->getContent();
        $this->assertCount(3, $images);
        $this->assertSame('first', $images[0]->getContent());
        $this->assertSame('second', $images[1]->getContent());
        $this->assertSame('third', $images[2]->getContent());
        $this->assertSame([
            'https://example.com/1.png',
            'https://example.com/2.png',
            'https://example.com/3.png',
        ], $result->getMetadata()->get('image_resource_urls'));
    }

    public function testItExposesTheGatewayCostAndProviderAsMetadata()
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'provider' => 'stabilityai',
            'cost' => '0.015000000',
            'output' => ['items' => [['image' => base64_encode('png'), 'image_resource_url' => 'https://example.com/a.png']]],
        ]));

        $this->assertSame('stabilityai', $result->getMetadata()->get('provider'));
        $this->assertSame(0.015, $result->getMetadata()->get('cost'));
        $this->assertSame(['https://example.com/a.png'], $result->getMetadata()->get('image_resource_urls'));
    }

    public function testItThrowsWhenProviderFails()
    {
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI request failed: "Prompt rejected."');

        $converter->convert($this->rawResult([
            'status' => 'fail',
            'output' => null,
            'error' => ['message' => 'Prompt rejected.'],
        ]));
    }

    public function testItThrowsWhenNoImageIsGenerated()
    {
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain a generated image.');

        $converter->convert($this->rawResult(['status' => 'success', 'output' => ['items' => []]]));
    }

    public function testItThrowsWhenOutputHasNoItemsKey()
    {
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain a generated image.');

        $converter->convert($this->rawResult(['status' => 'success', 'output' => []]));
    }

    public function testItThrowsWhenEveryItemLacksAnImage()
    {
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain a generated image.');

        $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['items' => [['image_resource_url' => 'https://example.com/a.png'], ['image' => '']]],
        ]));
    }

    public function testItThrowsWhenImageIsNotValidBase64()
    {
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The provided data is not valid base64-encoded data.');

        $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['items' => [['image' => '!!! not base64 !!!']]],
        ]));
    }

    public function testItOmitsMetadataWhenTheGatewayReportsNeitherCostNorProvider()
    {
        $converter = new ResultConverter();

        $result = $converter->convert($this->rawResult([
            'status' => 'success',
            'output' => ['items' => [['image' => base64_encode('png')]]],
        ]));

        $this->assertNull($result->getMetadata()->get('provider'));
        $this->assertNull($result->getMetadata()->get('cost'));
        $this->assertNull($result->getMetadata()->get('image_resource_urls'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rawResult(array $data): RawHttpResult
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($data));

        return new RawHttpResult($httpClient->request('POST', 'https://api.edenai.run/v3/universal-ai'));
    }
}
