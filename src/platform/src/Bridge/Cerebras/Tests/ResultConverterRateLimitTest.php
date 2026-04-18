<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cerebras\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterRateLimitTest extends TestCase
{
    public function testRateLimitExceededThrowsException()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"message":"Too many requests"}', [
                'http_code' => 429,
                'response_headers' => [
                    'retry-after' => '10',
                ],
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        try {
            $converter->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(10, $e->getRetryAfter());
            throw $e;
        }
    }

    public function testRateLimitExceededWithoutRetryAfter()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"message":"Too many requests"}', [
                'http_code' => 429,
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.cerebras.ai/v1/chat/completions');
        $converter = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        try {
            $converter->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
            throw $e;
        }
    }
}