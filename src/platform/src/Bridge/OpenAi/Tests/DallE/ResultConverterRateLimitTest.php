<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\DallE;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterRateLimitTest extends TestCase
{
    public function testRateLimitExceededThrowsException()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"message":"Rate limit reached","type":"requests","code":"rate_limit_exceeded"}}', [
                'http_code' => 429,
                'response_headers' => [
                    'retry-after' => '15',
                ],
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.openai.com/v1/images/generations');
        $converter = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        try {
            $converter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);
        } catch (RateLimitExceededException $e) {
            $this->assertSame(15, $e->getRetryAfter());
            throw $e;
        }
    }

    public function testRateLimitExceededWithoutRetryAfter()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"message":"Rate limit reached"}}', [
                'http_code' => 429,
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.openai.com/v1/images/generations');
        $converter = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        try {
            $converter->convert(new RawHttpResult($httpResponse), ['response_format' => 'url']);
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
            throw $e;
        }
    }
}