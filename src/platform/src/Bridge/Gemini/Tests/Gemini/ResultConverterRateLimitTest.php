<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterRateLimitTest extends TestCase
{
    public function testRateLimitExceededThrowsException()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":{"code":429,"message":"Resource has been exhausted (e.g. check quota).","status":"RESOURCE_EXHAUSTED"}}', [
                'http_code' => 429,
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');
        $handler = new ResultConverter();

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Resource has been exhausted (e.g. check quota).');

        try {
            $handler->convert(new RawHttpResult($httpResponse));
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
            throw $e;
        }
    }

    public function testRateLimitExceededReadsTheRetryDelayGeminiSends()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => [
                    'code' => 429,
                    'message' => 'You exceeded your current quota, please check your plan and billing details.',
                    'status' => 'RESOURCE_EXHAUSTED',
                    'details' => [
                        [
                            '@type' => 'type.googleapis.com/google.rpc.QuotaFailure',
                            'violations' => [['quotaMetric' => 'generativelanguage.googleapis.com/generate_content_free_tier_requests', 'quotaId' => 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier', 'quotaValue' => '15']],
                        ],
                        ['@type' => 'type.googleapis.com/google.rpc.Help', 'links' => [['description' => 'Learn more about Gemini API quotas', 'url' => 'https://ai.google.dev/gemini-api/docs/rate-limits']]],
                        ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '20s'],
                    ],
                ],
            ]), [
                'http_code' => 429,
            ]),
        ]);

        $httpResponse = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');

        try {
            (new ResultConverter())->convert(new RawHttpResult($httpResponse));
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(20, $e->getRetryAfter());
        }
    }
}
