<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ResultConverter;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ResultConverterTest extends TestCase
{
    public function testItSupportsMistralModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Mistral('mistral-large-latest')));
    }

    public function testConvertTextResult()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello world',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertTruncatedTextResult()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Truncated answ',
                    ],
                    'finish_reason' => 'length',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Truncated answ', $result->getContent());
        $this->assertTrue($result->getMetadata()->get('finish_reason')->is(FinishReasonCase::LENGTH));
    }

    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('maximum context length');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'message' => 'Prompt contains 300019 tokens and 0 draft tokens, too large for model with 262144 maximum context length',
        ], ['http_code' => 400]));

        $httpResponse = $httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsBadRequestExceptionOnOtherBadRequestErrors()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid model specified');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'message' => 'Invalid model specified',
        ], ['http_code' => 400]));

        $httpResponse = $httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['error' => 'Service Unavailable'], ['http_code' => 500]));
        $httpResponse = $httpClient->request('POST', 'https://example.com');
        $converter = new ResultConverter();

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 500');

        $converter->convert(new RawHttpResult($httpResponse), ['stream' => true]);
    }
}
