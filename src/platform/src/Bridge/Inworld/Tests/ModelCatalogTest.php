<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Inworld\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Inworld\Inworld;
use Symfony\AI\Platform\Bridge\Inworld\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelCatalogTest extends TestCase
{
    public function testReturnsEmptyWhenApiReturnsNoModels()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.inworld.ai/llm/v1alpha/models', $url);

            return new JsonMockResponse(['models' => []]);
        }, 'https://api.inworld.ai/');

        $models = (new ModelCatalog($httpClient))->getModels();

        $this->assertSame([], $models);
    }

    public function testReturnsEmptyWhenApiPayloadHasNoModelsKey()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.inworld.ai/');

        $models = (new ModelCatalog($httpClient))->getModels();

        $this->assertSame([], $models);
    }

    public function testGetModelsIsMemoized()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['models' => []]),
        ], 'https://api.inworld.ai/');

        $catalog = new ModelCatalog($httpClient);
        $catalog->getModels();
        $catalog->getModels();
        $catalog->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testReturnsTtsModelFromApiWithCorrectCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    [
                        'model' => 'inworld-tts-2',
                        'spec' => [
                            'inputModalities' => ['text'],
                            'outputModalities' => ['audio'],
                        ],
                    ],
                ],
            ]),
        ], 'https://api.inworld.ai/');

        $model = (new ModelCatalog($httpClient))->getModel('inworld-tts-2');

        $this->assertInstanceOf(Inworld::class, $model);
        $this->assertSame('inworld-tts-2', $model->getName());
        $this->assertSame([
            Capability::TEXT_TO_SPEECH,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_AUDIO,
        ], $model->getCapabilities());
    }

    public function testReturnsSttModelFromApiWithCorrectCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    [
                        'model' => 'inworld/inworld-stt-1',
                        'spec' => [
                            'inputModalities' => ['audio'],
                            'outputModalities' => ['text'],
                        ],
                    ],
                ],
            ]),
        ], 'https://api.inworld.ai/');

        $model = (new ModelCatalog($httpClient))->getModel('inworld/inworld-stt-1');

        $this->assertInstanceOf(Inworld::class, $model);
        $this->assertSame('inworld/inworld-stt-1', $model->getName());
        $this->assertSame([
            Capability::SPEECH_TO_TEXT,
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
        ], $model->getCapabilities());
    }

    public function testThrowsForModelNotReturnedByApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['models' => []]),
        ], 'https://api.inworld.ai/');

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" cannot be retrieved from the API.');
        $this->expectExceptionCode(0);
        $catalog->getModel('foo');
    }

    public function testThrowsForApiModelWithoutSupportedCapabilities()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    [
                        'model' => 'gemini-2.5-flash',
                        'spec' => [
                            'inputModalities' => ['text'],
                            'outputModalities' => ['text'],
                        ],
                    ],
                ],
            ]),
        ], 'https://api.inworld.ai/');

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "gemini-2.5-flash" is not supported, please check the Inworld API.');
        $this->expectExceptionCode(0);
        $catalog->getModel('gemini-2.5-flash');
    }

    public function testIgnoresApiEntriesWithMissingFields()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'models' => [
                    ['no-model-key' => true],
                    ['model' => 'no-spec'],
                    ['model' => 'invalid-spec', 'spec' => 'not-an-array'],
                    [
                        'model' => 'missing-modalities',
                        'spec' => [],
                    ],
                ],
            ]),
        ], 'https://api.inworld.ai/');

        $models = (new ModelCatalog($httpClient))->getModels();

        $this->assertArrayNotHasKey('no-model-key', $models);
        $this->assertArrayHasKey('no-spec', $models);
        $this->assertSame([], $models['no-spec']['capabilities']);
        $this->assertArrayHasKey('invalid-spec', $models);
        $this->assertSame([], $models['invalid-spec']['capabilities']);
        $this->assertArrayHasKey('missing-modalities', $models);
        $this->assertSame([], $models['missing-modalities']['capabilities']);
    }

    public function testIgnoresApiModelsListWhenNotArray()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['models' => 'not-an-array']),
        ], 'https://api.inworld.ai/');

        $models = (new ModelCatalog($httpClient))->getModels();

        $this->assertSame([], $models);
    }
}
