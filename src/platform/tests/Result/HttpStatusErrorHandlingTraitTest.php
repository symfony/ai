<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Pascal CESCON <pascal.cescon@gmail.com>
 */
final class HttpStatusErrorHandlingTraitTest extends TestCase
{
    /**
     * Any status outside the 400/401/429 trio - success, redirect, server-error -
     * is passed through untouched for the calling converter to handle.
     */
    #[DataProvider('unhandledStatusCodes')]
    public function testDoesNotThrowForUnhandledStatusCodes(int $status)
    {
        $this->expectNotToPerformAssertions();

        $this->subject()->throwOnHttpError($this->response('', $status));
    }

    /**
     * @return iterable<array{int}>
     */
    public static function unhandledStatusCodes(): iterable
    {
        yield '200 OK' => [200];
        yield '201 Created' => [201];
        yield '204 No Content' => [204];
        yield '301 Moved Permanently' => [301];
        yield '302 Found' => [302];
        yield '500 Internal Server Error' => [500];
        yield '503 Service Unavailable' => [503];
    }

    /**
     * Nested `error.message` is emitted by OpenAI, Gemini, Perplexity and DeepSeek.
     */
    public function testThrowsAuthenticationExceptionOn401WithNestedErrorMessage()
    {
        $response = $this->response(json_encode([
            'error' => [
                'message' => 'Invalid API key',
            ],
        ]), 401);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->subject()->throwOnHttpError($response);
    }

    /**
     * Flat top-level `message` is emitted by Mistral, Cerebras and Cohere.
     */
    public function testThrowsAuthenticationExceptionOn401WithFlatMessage()
    {
        $response = $this->response(json_encode([
            'message' => 'invalid api token',
        ]), 401);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid api token');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsAuthenticationExceptionOn401WithEmptyBody()
    {
        $response = $this->response('', 401);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsAuthenticationExceptionOn401WithInvalidJson()
    {
        $response = $this->response('not json at all', 401);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsBadRequestExceptionOn400WithNestedErrorMessage()
    {
        $response = $this->response(json_encode([
            'error' => [
                'message' => 'Invalid request body',
            ],
        ]), 400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid request body');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsBadRequestExceptionOn400WithFlatMessage()
    {
        $response = $this->response(json_encode([
            'message' => 'invalid model specified',
        ]), 400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('invalid model specified');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsBadRequestExceptionOn400WithEmptyBody()
    {
        $response = $this->response('', 400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsRateLimitExceededExceptionOn429WithRetryAfter()
    {
        $response = $this->response('{"message":"Too many requests"}', 429, ['retry-after' => '42']);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(42, $e->getRetryAfter());
        }
    }

    public function testThrowsRateLimitExceededExceptionOn429WithoutRetryAfter()
    {
        $response = $this->response('{"message":"Too many requests"}', 429);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }

    /**
     * RFC 7231 allows Retry-After as an HTTP-date. AI providers consistently use
     * delta-seconds in practice, so the trait intentionally ignores date-format
     * values rather than silently casting them to 0 ("retry now").
     */
    public function testIgnoresNonNumericRetryAfter()
    {
        $response = $this->response('{"message":"Too many requests"}', 429, [
            'retry-after' => 'Wed, 21 Oct 2015 07:28:00 GMT',
        ]);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function response(string $body, int $statusCode, array $headers = []): ResponseInterface
    {
        $client = new MockHttpClient([
            new MockResponse($body, [
                'http_code' => $statusCode,
                'response_headers' => $headers,
            ]),
        ]);

        return $client->request('GET', 'https://example.test/');
    }

    private function subject(): object
    {
        return new class {
            use HttpStatusErrorHandlingTrait {
                throwOnHttpError as public;
            }
        };
    }
}
