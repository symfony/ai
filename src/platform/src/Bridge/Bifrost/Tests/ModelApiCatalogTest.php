<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechModel;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionModel;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageModel;
use Symfony\AI\Platform\Bridge\Bifrost\ModelApiCatalog;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ModelApiCatalogTest extends TestCase
{
    public function testItHitsTheRemoteCatalogOnce()
    {
        $mock = new MockHttpClient([
            new JsonMockResponse(['data' => [
                [
                    'id' => 'openai/gpt-4o-mini',
                    'architecture' => [
                        'input_modalities' => ['text', 'image'],
                        'output_modalities' => ['text'],
                    ],
                ],
            ]]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $catalog->getModels();
        $catalog->getModels();

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItRoutesCompletionsModelFromCatalog()
    {
        $mock = new MockHttpClient([
            new JsonMockResponse(['data' => [
                [
                    'id' => 'openai/gpt-4o-mini',
                    'architecture' => [
                        'input_modalities' => ['text'],
                        'output_modalities' => ['text'],
                    ],
                    'supported_parameters' => ['tools', 'structured_outputs'],
                ],
            ]]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $model = $catalog->getModel('openai/gpt-4o-mini');

        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertContains(Capability::INPUT_TEXT, $model->getCapabilities());
        $this->assertContains(Capability::OUTPUT_TEXT, $model->getCapabilities());
        $this->assertContains(Capability::OUTPUT_STREAMING, $model->getCapabilities());
        $this->assertContains(Capability::TOOL_CALLING, $model->getCapabilities());
        $this->assertContains(Capability::OUTPUT_STRUCTURED, $model->getCapabilities());
    }

    public function testItRoutesEmbeddingsFromName()
    {
        $mock = new MockHttpClient([
            new JsonMockResponse(['data' => [
                ['id' => 'openai/text-embedding-3-small'],
            ]]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $model = $catalog->getModel('openai/text-embedding-3-small');

        $this->assertInstanceOf(EmbeddingsModel::class, $model);
        $this->assertContains(Capability::EMBEDDINGS, $model->getCapabilities());
    }

    public function testItRoutesSpeechFromModalities()
    {
        $mock = new MockHttpClient([
            new JsonMockResponse(['data' => [
                [
                    'id' => 'openai/tts-1',
                    'architecture' => [
                        'input_modalities' => ['text'],
                        'output_modalities' => ['audio'],
                    ],
                ],
            ]]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $model = $catalog->getModel('openai/tts-1');

        $this->assertInstanceOf(SpeechModel::class, $model);
        $this->assertContains(Capability::TEXT_TO_SPEECH, $model->getCapabilities());
    }

    public function testItRoutesTranscriptionFromModalities()
    {
        $mock = new MockHttpClient([
            new JsonMockResponse(['data' => [
                [
                    'id' => 'openai/whisper-1',
                    'architecture' => [
                        'input_modalities' => ['audio'],
                        'output_modalities' => ['text'],
                    ],
                ],
            ]]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $model = $catalog->getModel('openai/whisper-1');

        $this->assertInstanceOf(TranscriptionModel::class, $model);
        $this->assertContains(Capability::SPEECH_TO_TEXT, $model->getCapabilities());
    }

    public function testItRoutesImageFromModalities()
    {
        $mock = new MockHttpClient([
            new JsonMockResponse(['data' => [
                [
                    'id' => 'openai/dall-e-3',
                    'architecture' => [
                        'input_modalities' => ['text'],
                        'output_modalities' => ['image'],
                    ],
                ],
            ]]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $model = $catalog->getModel('openai/dall-e-3');

        $this->assertInstanceOf(ImageModel::class, $model);
        $this->assertContains(Capability::TEXT_TO_IMAGE, $model->getCapabilities());
    }

    public function testItFallsBackOnNameWhenModelNotInCatalog()
    {
        $mock = new MockHttpClient([
            new JsonMockResponse(['data' => []]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));

        $this->assertInstanceOf(CompletionsModel::class, $catalog->getModel('anthropic/claude-3-opus'));
        $this->assertInstanceOf(EmbeddingsModel::class, $catalog->getModel('cohere/embed-english-v3'));
        $this->assertInstanceOf(TranscriptionModel::class, $catalog->getModel('openai/whisper-1'));
        $this->assertInstanceOf(SpeechModel::class, $catalog->getModel('openai/tts-1'));
        $this->assertInstanceOf(ImageModel::class, $catalog->getModel('openai/dall-e-3'));
        $this->assertInstanceOf(ImageModel::class, $catalog->getModel('google/imagen-3'));
        $this->assertInstanceOf(ImageModel::class, $catalog->getModel('black-forest-labs/flux-1.1-pro'));
    }

    public function testItFallsBackSilentlyOnHttpFailure()
    {
        $mock = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $model = $catalog->getModel('anthropic/claude-3-opus');

        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('anthropic/claude-3-opus', $model->getName());
    }

    public function testItUsesScopedClientBaseUriAndAuthBearer()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame('GET', $method);
                self::assertSame('http://localhost:8080/v1/models', $url);

                $headers = $options['normalized_headers'] ?? [];
                self::assertIsArray($headers);
                self::assertArrayHasKey('authorization', $headers);
                self::assertIsArray($headers['authorization']);
                self::assertSame('Authorization: Bearer sk-bf-test', $headers['authorization'][0]);

                return new MockResponse('{"data":[]}', ['http_code' => 200]);
            },
        ]);

        $catalog = new ModelApiCatalog(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080', [
            'auth_bearer' => 'sk-bf-test',
        ]));
        $catalog->getModels();

        $this->assertSame(1, $mock->getRequestsCount());
    }
}
