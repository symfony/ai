<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DeepSeek\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DeepSeek\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterRateLimitTest extends TestCase
{
    public function testRateLimitExceededThrowsException()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"message":"You have exceeded your rate limit"}}', [
                'http_code' => 429,
                'response_headers' => [
                    'retry-after' => '45',
                ],
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.deepseek.com/chat/completions');
        $converter = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        try {
            $converter->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(45, $e->getRetryAfter());
            throw $e;
        }
    }

    public function testRateLimitExceededWithoutRetryAfter()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"message":"You have exceeded your rate limit"}}', [
                'http_code' => 429,
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.deepseek.com/chat/completions');
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