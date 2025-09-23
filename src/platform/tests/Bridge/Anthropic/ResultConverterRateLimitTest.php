<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ResultConverter::class)]
#[Small]
final class ResultConverterRateLimitTest extends TestCase
{
    public function testRateLimitExceededThrowsException()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"type":"error","error":{"type":"rate_limit_error","message":"This request would exceed the rate limit for your organization"}}', [
                'http_code' => 429,
                'response_headers' => [
                    'retry-after' => '60',
                ],
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $handler = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        try {
            $handler->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertSame(60.0, $e->getRetryAfter());
            throw $e;
        }
    }

    public function testRateLimitExceededWithoutRetryAfter()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"type":"error","error":{"type":"rate_limit_error","message":"This request would exceed the rate limit for your organization"}}', [
                'http_code' => 429,
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $handler = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        try {
            $handler->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
            throw $e;
        }
    }
}
