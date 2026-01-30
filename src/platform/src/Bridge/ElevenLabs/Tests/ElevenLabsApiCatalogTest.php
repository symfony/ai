<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsApiCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ElevenLabsApiCatalogTest extends TestCase
{
    public function testModelCatalogCannotReturnModelFromApiWhenUndefined()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ]);

        $modelCatalog = new ElevenLabsApiCatalog($httpClient, 'foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" cannot be retrieved from the API.');
        $this->expectExceptionCode(0);
        $modelCatalog->getModel('foo');
    }

    public function testModelCatalogCannotReturnUnsupportedModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => false,
                ],
            ]),
        ]);

        $modelCatalog = new ElevenLabsApiCatalog($httpClient, 'foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" is not supported, please check the ElevenLabs API.');
        $this->expectExceptionCode(0);
        $modelCatalog->getModel('foo');
    }

    public function testModelCatalogCanReturnSpecificTtsModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => true,
                    'can_do_voice_conversion' => false,
                ],
                [
                    'model_id' => 'bar',
                    'name' => 'bar',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => true,
                ],
            ]),
        ]);

        $modelCatalog = new ElevenLabsApiCatalog($httpClient, 'foo');

        $model = $modelCatalog->getModel('foo');

        $this->assertSame('foo', $model->getName());
        $this->assertSame([
            Capability::TEXT_TO_SPEECH,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_AUDIO,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnSpecificSttModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => true,
                ],
            ]),
        ]);

        $modelCatalog = new ElevenLabsApiCatalog($httpClient, 'foo');

        $model = $modelCatalog->getModel('foo');

        $this->assertSame('foo', $model->getName());
        $this->assertSame([
            Capability::SPEECH_TO_TEXT,
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
        ], $model->getCapabilities());

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testModelCatalogCanReturnModelsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => false,
                    'can_do_voice_conversion' => true,
                ],
                [
                    'model_id' => 'bar',
                    'name' => 'bar',
                    'can_do_text_to_speech' => true,
                    'can_do_voice_conversion' => false,
                ],
            ]),
        ]);

        $modelCatalog = new ElevenLabsApiCatalog($httpClient, 'foo');

        $models = $modelCatalog->getModels();

        $this->assertCount(2, $models);
        $this->assertArrayHasKey('foo', $models);
        $this->assertArrayHasKey('bar', $models);
        $this->assertSame(ElevenLabs::class, $models['foo']['class']);
        $this->assertCount(3, $models['foo']['capabilities']);
        $this->assertSame([
            Capability::SPEECH_TO_TEXT,
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
        ], $models['foo']['capabilities']);
        $this->assertSame(ElevenLabs::class, $models['bar']['class']);
        $this->assertCount(3, $models['bar']['capabilities']);
        $this->assertSame([
            Capability::TEXT_TO_SPEECH,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_AUDIO,
        ], $models['bar']['capabilities']);
    }
}
