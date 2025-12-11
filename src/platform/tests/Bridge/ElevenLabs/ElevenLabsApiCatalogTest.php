<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\ElevenLabs;

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
        $this->expectExceptionMessage('The model "foo" cannot be retrieve from the API.');
        $this->expectExceptionCode(0);
        $modelCatalog->getModel('foo');
    }

    public function testModelCatalogCanReturnModelFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => true,
                    'can_do_voice_conversion' => false,
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

    public function testModelCatalogCanReturnModelsFromApi()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'foo',
                    'name' => 'foo',
                    'can_do_text_to_speech' => true,
                    'can_do_voice_conversion' => false,
                ],
            ]),
        ]);

        $modelCatalog = new ElevenLabsApiCatalog($httpClient, 'foo');

        $models = $modelCatalog->getModels();

        $this->assertCount(1, $models);
        $this->assertArrayHasKey('foo', $models);
        $this->assertSame(ElevenLabs::class, $models['foo']['class']);
        $this->assertCount(3, $models['foo']['capabilities']);
        $this->assertSame([
            Capability::TEXT_TO_SPEECH,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_AUDIO,
        ], $models['foo']['capabilities']);
    }
}
