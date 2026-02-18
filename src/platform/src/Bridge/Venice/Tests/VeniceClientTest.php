<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\Venice;
use Symfony\AI\Platform\Bridge\Venice\VeniceClient;
use Symfony\AI\Platform\Capability;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class VeniceClientTest extends TestCase
{
    public function testClientCanTriggerCompletion()
    {
    }

    public function testClientCanTriggerTextToSpeech()
    {
    }

    public function testClientCanTriggerEmbeddings()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'embedding' => [
                            0.0023064255,
                            -0.009327292,
                            0.015797377,
                        ],
                        'index' => 0,
                        'object' => 'embedding',
                    ],
                ],
                'model' => 'text-embedding-bge-m3',
                'object' => 'list',
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(new Venice('text-embedding-bge-m3', [
            Capability::EMBEDDINGS,
        ]), 'foo');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
