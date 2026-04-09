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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelCatalogTest extends TestCase
{
    public function testCatalogCannotRetrieveUndefinedModel()
    {
        $catalog = new ModelCatalog(new MockHttpClient([
            new JsonMockResponse([
                'tts' => [],
                'stt' => [],
            ]),
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model "foo" does not exist.');
        $this->expectExceptionCode(0);
        $catalog->getModel('foo');
    }

    public function testCatalogCanReturnTextToSpeechModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [
                    [
                        'name' => 'nova-3',
                        'canonical_name' => 'nova-3',
                        'architecture' => 'base',
                        'languages' => [
                            'en-us',
                            'en',
                        ],
                    ],
                ],
                'stt' => [],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('nova-3');

        $this->assertSame('nova-3', $model->getName());
        $this->assertSame([
            Capability::INPUT_TEXT,
            Capability::TEXT_TO_SPEECH,
            Capability::OUTPUT_AUDIO,
        ], $model->getCapabilities());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testCatalogCanReturnSpeechToTextModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'tts' => [],
                'stt' => [
                    [
                        'name' => 'zeus',
                        'canonical_name' => 'aura-2-zeus-en',
                        'architecture' => 'aura-2',
                        'languages' => [
                            'en-us',
                            'en',
                        ],
                    ],
                ],
            ]),
        ]);

        $catalog = new ModelCatalog($httpClient);
        $model = $catalog->getModel('zeus');

        $this->assertSame('zeus', $model->getName());
        $this->assertSame([
            Capability::INPUT_AUDIO,
            Capability::SPEECH_TO_TEXT,
            Capability::OUTPUT_TEXT,
        ], $model->getCapabilities());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
