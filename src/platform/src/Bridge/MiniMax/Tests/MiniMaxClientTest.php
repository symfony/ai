<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMax;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMaxClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

final class MiniMaxClientTest extends TestCase
{
    public function testClientCannotGenerateTextOnStringInput()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(),
        );

        $client = new MiniMaxClient($httpClient, 'foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload is not an array, given "string".');
        $this->expectExceptionCode(0);
        $client->request(new MiniMax('M2-her', [
            Capability::INPUT_MESSAGES,
        ]), 'foo');
    }

    public function testClientCanGenerateText()
    {
        $payload = [
            'id' => Uuid::v7()->toRfc4122(),
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'index' => 0,
                    'message' => [
                        'content' => 'Generated text',
                        'role' => 'assistant',
                        'name' => 'MiniMax AI',
                        'audio_content' => '',
                    ],
                ],
            ],
            'created' => (new \DateTimeImmutable())->getTimestamp(),
            'model' => 'M2-her',
            'object' => 'chat.completion',
        ];

        $httpClient = new MockHttpClient(
            new JsonMockResponse($payload),
        );

        $client = new MiniMaxClient($httpClient, 'foo');

        $result = $client->request(new MiniMax('M2-her', [
            Capability::INPUT_MESSAGES,
        ]), [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'foo',
                ],
                [
                    'role' => 'user',
                    'content' => 'bar',
                ],
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame($payload, $result->getData());
    }

    public function testClientCanGenerateTextAsStream()
    {
        $payload = [
            [
                'id' => Uuid::v7()->toRfc4122(),
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'index' => 0,
                        'message' => [
                            'content' => 'Generated text',
                            'role' => 'assistant',
                            'name' => 'MiniMax AI',
                            'audio_content' => '',
                        ],
                    ],
                ],
                'created' => (new \DateTimeImmutable())->getTimestamp(),
                'model' => 'M2-her',
                'object' => 'chat.completion.chunk',
            ],
            [
                'id' => Uuid::v7()->toRfc4122(),
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'index' => 1,
                        'message' => [
                            'content' => 'Second generated text',
                            'role' => 'assistant',
                            'name' => 'MiniMax AI',
                            'audio_content' => '',
                        ],
                    ],
                ],
                'created' => (new \DateTimeImmutable())->getTimestamp(),
                'model' => 'M2-her',
                'object' => 'chat.completion.chunk',
            ],
        ];

        $httpClient = new MockHttpClient(
            new JsonMockResponse($payload),
        );

        $client = new MiniMaxClient($httpClient, 'foo');

        $result = $client->request(new MiniMax('M2-her', [
            Capability::INPUT_MESSAGES,
        ]), [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'foo',
                ],
                [
                    'role' => 'user',
                    'content' => 'bar',
                ],
            ],
        ], [
            'stream' => true,
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame($payload, $result->getData());
    }
}
