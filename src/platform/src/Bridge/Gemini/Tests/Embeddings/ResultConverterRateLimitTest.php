<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\ResultConverter;
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

        $httpResponse = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent');
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