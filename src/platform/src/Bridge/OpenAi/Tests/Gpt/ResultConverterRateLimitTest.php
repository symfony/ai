<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Gpt;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterRateLimitTest extends TestCase
{
    #[TestWith(['20ms', 1])]
    #[TestWith(['8.64s', 9])]
    #[TestWith(['1h2m3s', 3723])]
    #[TestWith(['2m59.56s', 180])]
    public function testRateLimitExceededWithGoDurationResetTime(string $resetTime, int $retryAfter)
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"message":"Rate limit reached for tokens","type":"rate_limit_error"}}', [
                'http_code' => 429,
                'response_headers' => [
                    'x-ratelimit-reset-requests' => $resetTime,
                ],
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions');

        try {
            (new ResultConverter())->convert(new RawHttpResult($httpResponse));
            $this->fail('A 429 must throw.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame($retryAfter, $e->getRetryAfter());
        }
    }

    public function testRateLimitExceededWithRequestsResetTime()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"message":"Rate limit reached for requests","type":"rate_limit_error"}}', [
                'http_code' => 429,
                'response_headers' => [
                    'x-ratelimit-limit-requests' => '60',
                    'x-ratelimit-remaining-requests' => '0',
                    'x-ratelimit-reset-requests' => '20s',
                ],
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions');
        $handler = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Rate limit reached for requests');

        try {
            $handler->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(20, $e->getRetryAfter());
            throw $e;
        }
    }

    public function testRateLimitExceededWithTokensResetTime()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"message":"Rate limit reached for tokens","type":"rate_limit_error"}}', [
                'http_code' => 429,
                'response_headers' => [
                    'x-ratelimit-limit-tokens' => '150000',
                    'x-ratelimit-remaining-tokens' => '0',
                    'x-ratelimit-reset-tokens' => '2m30s',
                ],
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions');
        $handler = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Rate limit reached for tokens');

        try {
            $handler->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(150, $e->getRetryAfter());
            throw $e;
        }
    }
}
