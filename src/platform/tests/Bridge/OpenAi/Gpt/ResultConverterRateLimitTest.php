<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Gpt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ResultConverter::class)]
#[Small]
final class ResultConverterRateLimitTest extends TestCase
{
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
        $this->expectExceptionMessage('Rate limit exceeded.');

        try {
            $handler->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(20.0, $e->getRetryAfter());
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
        $this->expectExceptionMessage('Rate limit exceeded.');

        try {
            $handler->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(150.0, $e->getRetryAfter());
            throw $e;
        }
    }
}
