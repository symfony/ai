<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelCatalogTest extends TestCase
{
    public function testCatalogCannotRetrieveUndefinedModel()
    {
        $catalog = new ModelCatalog(new MockHttpClient([
            new JsonMockResponse(['tts' => [], 'stt' => []]),
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model "foo" does not exist.');
        $catalog->getModel('foo');
    }

    public function testCatalogReturnsTextToSpeechModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [
                    [
                        'name' => 'aura-2-thalia-en',
                        'canonical_name' => 'aura-2-thalia-en',
                        'architecture' => 'aura-2',
                        'languages' => ['en-us', 'en'],
                    ],
                ],
                'stt' => [],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('aura-2-thalia-en');

        $this->assertSame('aura-2-thalia-en', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::TEXT_TO_SPEECH,
            Capability::OUTPUT_AUDIO,
        ], $model->getCapabilities());
    }

    public function testCatalogReturnsSpeechToTextModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [],
                'stt' => [
                    [
                        'name' => 'nova-3',
                        'canonical_name' => 'nova-3',
                        'architecture' => 'nova-3',
                        'languages' => ['en-us', 'en'],
                    ],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('nova-3');

        $this->assertSame('nova-3', $model->getName());
        $this->assertSame([
            Capability::INPUT_AUDIO,
            Capability::SPEECH_TO_TEXT,
            Capability::OUTPUT_TEXT,
        ], $model->getCapabilities());
    }

    public function testLookupByCanonicalNameAndShortName()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [],
                'stt' => [
                    [
                        'name' => 'zeus',
                        'canonical_name' => 'aura-2-zeus-en',
                        'architecture' => 'aura-2',
                        'languages' => ['en-us'],
                    ],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);

        $shortLookup = $catalog->getModel('zeus');
        $canonicalLookup = $catalog->getModel('aura-2-zeus-en');

        $this->assertSame('zeus', $shortLookup->getName());
        $this->assertSame('aura-2-zeus-en', $canonicalLookup->getName());
        $this->assertSame($shortLookup->getCapabilities(), $canonicalLookup->getCapabilities());
    }

    public function testCatalogResultIsMemoized()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [
                    ['name' => 'aura-2-thalia-en', 'canonical_name' => 'aura-2-thalia-en'],
                ],
                'stt' => [
                    ['name' => 'nova-3', 'canonical_name' => 'nova-3'],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);

        $catalog->getModel('aura-2-thalia-en');
        $catalog->getModel('nova-3');
        $catalog->getModels();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testHandlesMissingTopLevelKeys()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [
                    ['name' => 'aura-2-thalia-en', 'canonical_name' => 'aura-2-thalia-en'],
                ],
                // 'stt' missing entirely
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);

        $this->assertSame(['aura-2-thalia-en'], array_keys($catalog->getModels()));
    }

    public function testRaisesRuntimeExceptionOnHttpError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Unauthorized', ['http_code' => 401]),
        ]);

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deepgram returned status "401" while listing models.');

        $catalog->getModels();
    }

    public function testRaisesRuntimeExceptionOnMalformedJson()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('not json', ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        $catalog = new ModelCatalog($httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deepgram returned a malformed JSON payload while listing models.');

        $catalog->getModels();
    }

    public function testIgnoresEmptyOrMissingIdentifiers()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [
                    ['name' => '', 'canonical_name' => 'aura-2-thalia-en'],
                ],
                'stt' => [
                    ['canonical_name' => 'nova-3'],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);

        $models = $catalog->getModels();
        $this->assertArrayHasKey('aura-2-thalia-en', $models);
        $this->assertArrayHasKey('nova-3', $models);
        $this->assertArrayNotHasKey('', $models);
    }
}
