<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Higgsfield\Higgsfield;
use Symfony\AI\Platform\Bridge\Higgsfield\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelCatalogTest extends TestCase
{
    private const BASE_URL = 'https://platform.higgsfield.ai';

    private const MODELS_FIXTURE = [
        'total' => 6,
        'items' => [
            ['slug' => 'higgsfield-ai/soul/v2/standard', 'operation_type' => ['text2image'], 'output_type' => 'image'],
            ['slug' => 'ideogram/v4.0', 'operation_type' => ['image_edit', 'text2image'], 'output_type' => 'image'],
            ['slug' => 'kling-video/v3.0/pro/image-to-video', 'operation_type' => ['image2video'], 'output_type' => 'video'],
            ['slug' => 'wan/v2.7/text-to-video', 'operation_type' => ['text2video'], 'output_type' => 'video'],
            ['slug' => 'bytedance/seedance-2.5/video-edit', 'operation_type' => ['video2video'], 'output_type' => 'video'],
            ['slug' => 'soul-id', 'operation_type' => ['character'], 'output_type' => 'image'],
        ],
    ];

    public function testItRetrievesModelsFromTheApi()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL);
        $catalog = new ModelCatalog($httpClient);

        $models = $catalog->getModels();

        $this->assertCount(6, $models);
        $this->assertArrayHasKey('higgsfield-ai/soul/v2/standard', $models);
        $this->assertSame(Higgsfield::class, $models['higgsfield-ai/soul/v2/standard']['class']);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItMapsTextToImageOperation()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $model = $catalog->getModel('higgsfield-ai/soul/v2/standard');

        $this->assertInstanceOf(Higgsfield::class, $model);
        $this->assertSame('higgsfield-ai/soul/v2/standard', $model->getName());
        $this->assertSame([Capability::TEXT_TO_IMAGE], $model->getCapabilities());
    }

    public function testItMapsSeveralOperationsOfASingleModel()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $model = $catalog->getModel('ideogram/v4.0');

        $this->assertSame([Capability::IMAGE_TO_IMAGE, Capability::TEXT_TO_IMAGE], $model->getCapabilities());
    }

    public function testItMapsImageToVideoOperation()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $model = $catalog->getModel('kling-video/v3.0/pro/image-to-video');

        $this->assertSame([Capability::IMAGE_TO_VIDEO], $model->getCapabilities());
    }

    public function testItMapsTextToVideoOperation()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $model = $catalog->getModel('wan/v2.7/text-to-video');

        $this->assertSame([Capability::TEXT_TO_VIDEO], $model->getCapabilities());
    }

    public function testItMapsVideoToVideoOperation()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $model = $catalog->getModel('bytedance/seedance-2.5/video-edit');

        $this->assertSame([Capability::VIDEO_TO_VIDEO], $model->getCapabilities());
    }

    public function testItLeavesAnUnmappedOperationWithoutCapabilities()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $model = $catalog->getModel('soul-id');

        $this->assertSame([], $model->getCapabilities());
    }

    public function testItParsesOptionsFromTheModelName()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $model = $catalog->getModel('higgsfield-ai/soul/v2/standard?aspect_ratio=16:9');

        $this->assertSame('higgsfield-ai/soul/v2/standard', $model->getName());
        $this->assertSame([PlatformOptions::PROVIDER_OPTIONS => ['aspect_ratio' => '16:9']], $model->getOptions());
    }

    public function testItThrowsWhenModelIsUnknown()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL));

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model "unknown/model" not found in "Symfony\\AI\\Platform\\Bridge\\Higgsfield\\ModelCatalog".');

        $catalog->getModel('unknown/model');
    }

    public function testItThrowsWhenTheApiReturnsAnError()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse([], ['http_code' => 500])], self::BASE_URL));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot retrieve models from the Higgsfield API (Status code: 500).');

        $catalog->getModels();
    }

    public function testItIgnoresMalformedEntries()
    {
        $catalog = new ModelCatalog(new MockHttpClient([new JsonMockResponse([
            'items' => [
                ['slug' => 'valid/model', 'operation_type' => ['text2image']],
                ['operation_type' => ['text2image']],
                'not-an-array',
                ['slug' => 123, 'operation_type' => ['text2image']],
            ],
        ])], self::BASE_URL));

        $models = $catalog->getModels();

        $this->assertArrayHasKey('valid/model', $models);
        $this->assertCount(1, $models);
    }

    public function testItMemoizesTheApiCall()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse(self::MODELS_FIXTURE)], self::BASE_URL);
        $catalog = new ModelCatalog($httpClient);

        $catalog->getModels();
        $catalog->getModel('higgsfield-ai/soul/v2/standard');
        $catalog->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
