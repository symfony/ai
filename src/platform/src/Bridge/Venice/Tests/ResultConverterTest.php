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
use Symfony\AI\Platform\Bridge\Venice\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testConvertCompletionToTextResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'index' => 0,
                        'message' => [
                            'content' => 'Hello! How can I help you?',
                            'role' => 'assistant',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                    'total_tokens' => 18,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'chat/completions');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame('Hello! How can I help you?', $converted->getContent());
    }

    public function testConvertStreamingCompletionToStreamResult()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getInfo')->willReturnCallback(static function (string $key) {
            if ('url' === $key) {
                return 'https://api.venice.ai/api/v1/chat/completions';
            }

            return null;
        });

        $streamData = [
            ['choices' => [['delta' => ['content' => 'Hello']]]],
            ['choices' => [['delta' => ['content' => ' world']]]],
            ['choices' => [['delta' => ['content' => '!']]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8]],
        ];

        $result = new InMemoryRawResult([], $streamData, $response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $converted);

        $chunks = [];
        foreach ($converted->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertSame('Hello', $chunks[0]);
        $this->assertSame(' world', $chunks[1]);
        $this->assertSame('!', $chunks[2]);
        $this->assertInstanceOf(TokenUsage::class, $chunks[3]);
        $this->assertSame(5, $chunks[3]->getPromptTokens());
        $this->assertSame(3, $chunks[3]->getCompletionTokens());
        $this->assertSame(8, $chunks[3]->getTotalTokens());
    }

    public function testConvertSpeechToBinaryResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'audio/speech');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(BinaryResult::class, $converted);
    }

    public function testConvertEmbeddingsToVectorResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'embedding' => [0.1, 0.2, 0.3],
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

        $response = $httpClient->request('POST', 'embeddings');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(VectorResult::class, $converted);
    }

    public function testConvertImageGenerationToTextResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'url' => 'https://venice.ai/images/generated/123.png',
                    ],
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'images/generations');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame('https://venice.ai/images/generated/123.png', $converted->getContent());
    }

    public function testConvertUnsupportedThrowsException()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'unknown/endpoint');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported model capability.');

        $converter->convert($result);
    }
}
