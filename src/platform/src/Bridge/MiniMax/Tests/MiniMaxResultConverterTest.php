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
use Symfony\AI\Platform\Bridge\MiniMax\MiniMaxResultConverter;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

final class MiniMaxResultConverterTest extends TestCase
{
    public function testConverterCanConvertTextGeneration()
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

        $httpResult = $client->request(new MiniMax('M2-her', [
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
        $this->assertSame($payload, $httpResult->getData());

        $converter = new MiniMaxResultConverter();

        $result = $converter->convert($httpResult, []);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Generated text', $result->getContent());
    }

    public function testConverterCanConvertTextGenerationAsStream()
    {
        $payload = [
            [
                'id' => Uuid::v7()->toRfc4122(),
                'choices' => [
                    [
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
                        'index' => 0,
                        'message' => [
                            'content' => 'and a second generated text',
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
                        'index' => 0,
                        'message' => [
                            'content' => 'Generated text and a second generated text',
                            'role' => 'assistant',
                            'name' => 'MiniMax AI',
                            'audio_content' => '',
                        ],
                    ],
                ],
                'created' => (new \DateTimeImmutable())->getTimestamp(),
                'model' => 'M2-her',
                'object' => 'chat.completion',
                'base_rep' => [
                    'status_code' => 0,
                    'status_msg' => '',
                ],
            ],
        ];

        $httpClient = new MockHttpClient(
            new JsonMockResponse($payload),
        );

        $client = new MiniMaxClient($httpClient, 'foo');

        $httpResult = $client->request(new MiniMax('M2-her', [
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
        $this->assertSame($payload, $httpResult->getData());

        $converter = new MiniMaxResultConverter();

        $result = $converter->convert($httpResult, []);

        $this->assertInstanceOf(StreamResult::class, $result);
    }
}
