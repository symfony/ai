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
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
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
     * Any status outside the handled 400/401/404/422/429/5xx set - success,
     * redirect - is passed through untouched for the calling converter to handle.
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
    }

    #[DataProvider('serverErrorStatusCodes')]
    public function testThrowsServerExceptionOnServerErrorStatus(int $status)
    {
        $response = $this->response(json_encode([
            'error' => [
                'message' => 'Service down',
            ],
        ]), $status);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected ServerException.');
        } catch (ServerException $e) {
            $this->assertSame($status, $e->getStatusCode());
            $this->assertSame(\sprintf('Server error (HTTP %d). HTTP %d returned for "https://example.test/": Service down', $status, $status), $e->getMessage());
        }
    }

    /**
     * @return iterable<array{int}>
     */
    public static function serverErrorStatusCodes(): iterable
    {
        yield '500 Internal Server Error' => [500];
        yield '502 Bad Gateway' => [502];
        yield '503 Service Unavailable' => [503];
        yield '529 Overloaded' => [529];
    }

    public function testThrowsServerExceptionOn5xxWithEmptyBody()
    {
        try {
            $this->subject()->throwOnHttpError($this->response('', 503));
            $this->fail('Expected ServerException.');
        } catch (ServerException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame('Server error (HTTP 503). HTTP 503 returned for "https://example.test/".', $e->getMessage());
        }
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
        $this->expectExceptionMessage('HTTP 401 returned for "https://example.test/": Invalid API key');

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
        $this->expectExceptionMessage('HTTP 401 returned for "https://example.test/": invalid api token');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsAuthenticationExceptionOn401WithEmptyBody()
    {
        $response = $this->response('', 401);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('HTTP 401 returned for "https://example.test/".');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsAuthenticationExceptionOn401WithInvalidJson()
    {
        $response = $this->response('not json at all', 401);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('HTTP 401 returned for "https://example.test/".');

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
        $this->expectExceptionMessage('HTTP 400 returned for "https://example.test/": Invalid request body');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsBadRequestExceptionOn400WithFlatMessage()
    {
        $response = $this->response(json_encode([
            'message' => 'invalid model specified',
        ]), 400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://example.test/": invalid model specified');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsBadRequestExceptionOn400WithEmptyBody()
    {
        $response = $this->response('', 400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://example.test/".');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsModelNotFoundExceptionOn404WithNestedErrorMessage()
    {
        $response = $this->response(json_encode([
            'error' => [
                'message' => 'The model `foo` does not exist',
            ],
        ]), 404);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "https://example.test/": The model `foo` does not exist');

        $this->subject()->throwOnHttpError($response);
    }

    /**
     * Flat top-level `message` is emitted by Mistral, Cerebras and Cohere.
     */
    public function testThrowsModelNotFoundExceptionOn404WithFlatMessage()
    {
        $response = $this->response(json_encode([
            'message' => 'Model llama3.1-8b does not exist or you do not have access to it.',
        ]), 404);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "https://example.test/": Model llama3.1-8b does not exist or you do not have access to it.');

        $this->subject()->throwOnHttpError($response);
    }

    public function testThrowsModelNotFoundExceptionOn404WithEmptyBody()
    {
        $response = $this->response('', 404);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "https://example.test/".');

        $this->subject()->throwOnHttpError($response);
    }

    public function testDropsTheQueryAndTheCredentialsOfTheRequestUrl()
    {
        $response = $this->response(
            '{"message":"invalid model specified"}',
            400,
            url: 'https://user:password@example.test:8443/v1/models/gemini-2.5-flash:generateContent?key=api-key',
        );

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected BadRequestException.');
        } catch (BadRequestException $e) {
            $this->assertSame('HTTP 400 returned for "https://example.test:8443/v1/models/gemini-2.5-flash:generateContent": invalid model specified', $e->getMessage());
        }
    }

    public function testOmitsTheRequestUrlWhenTheResponseHasNone()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getInfo')->willReturn(null);
        $response->method('toArray')->willReturn(['error' => ['message' => 'Invalid request body']]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('HTTP 400 returned: Invalid request body');

        $this->subject()->throwOnHttpError($response);
    }

    /**
     * FastAPI's HTTPValidationError, which Mistral documents for its 422 responses.
     */
    public function testThrowsBadRequestExceptionOn422WithValidationErrors()
    {
        $response = $this->response(json_encode([
            'detail' => [
                ['type' => 'enum', 'loc' => ['body', 'response_format'], 'msg' => 'Input should be \'mp3\' or \'wav\'', 'input' => 'nope'],
                ['type' => 'missing', 'loc' => ['body', 'messages', 0, 'content'], 'msg' => 'Field required'],
                ['type' => 'missing', 'loc' => 'body', 'msg' => 'Ignored, as the location is not a list'],
            ],
        ]), 422);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected BadRequestException.');
        } catch (BadRequestException $e) {
            $this->assertSame('HTTP 422 returned for "https://example.test/": body.response_format: Input should be \'mp3\' or \'wav\'; body.messages.0.content: Field required', $e->getMessage());
        }
    }

    public function testReadsValidationErrorsNestedUnderTheMessage()
    {
        $response = $this->response(json_encode([
            'object' => 'error',
            'message' => [
                'detail' => [
                    ['type' => 'extra_forbidden', 'loc' => ['body', 'bogus_field'], 'msg' => 'Extra inputs are not permitted', 'input' => 1],
                ],
            ],
            'type' => 'invalid_request_error',
            'param' => null,
            'code' => null,
        ]), 422);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected BadRequestException.');
        } catch (BadRequestException $e) {
            $this->assertSame('HTTP 422 returned for "https://example.test/": body.bogus_field: Extra inputs are not permitted', $e->getMessage());
        }
    }

    public function testReadsAFastApiDetailMessage()
    {
        $response = $this->response('{"detail":"Not Found"}', 404);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "https://example.test/": Not Found');

        $this->subject()->throwOnHttpError($response);
    }

    public function testIgnoresAMessageThatIsNotAString()
    {
        $response = $this->response('{"message":{"code":"invalid"}}', 400);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected BadRequestException.');
        } catch (BadRequestException $e) {
            $this->assertSame('HTTP 400 returned for "https://example.test/".', $e->getMessage());
        }
    }

    public function testThrowsRateLimitExceededExceptionOn429WithRetryAfter()
    {
        $response = $this->response('{"message":"Too many requests"}', 429, ['retry-after' => '42']);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(42, $e->getRetryAfter());
            $this->assertSame('Rate limit exceeded. HTTP 429 returned for "https://example.test/": Too many requests', $e->getMessage());
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
            $this->assertSame('Rate limit exceeded. HTTP 429 returned for "https://example.test/": Too many requests', $e->getMessage());
        }
    }

    /**
     * Nested `error.message` is emitted by OpenAI, Anthropic, Gemini and others.
     */
    public function testThrowsRateLimitExceededExceptionOn429WithNestedErrorMessage()
    {
        $response = $this->response(json_encode([
            'error' => [
                'message' => 'You exceeded your current quota, please check your plan and billing details.',
            ],
        ]), 429);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('Rate limit exceeded. HTTP 429 returned for "https://example.test/": You exceeded your current quota, please check your plan and billing details.', $e->getMessage());
        }
    }

    public function testThrowsRateLimitExceededExceptionOn429WithEmptyBody()
    {
        $response = $this->response('', 429);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('Rate limit exceeded. HTTP 429 returned for "https://example.test/".', $e->getMessage());
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

    #[DataProvider('retryInfoDelays')]
    public function testReadsTheRetryDelayOfAGoogleRetryInfoDetail(string $retryDelay, int $expected)
    {
        $response = $this->response(json_encode([
            'error' => [
                'code' => 429,
                'message' => 'You exceeded your current quota, please check your plan and billing details.',
                'status' => 'RESOURCE_EXHAUSTED',
                'details' => [
                    ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier']]],
                    ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => $retryDelay],
                ],
            ],
        ]), 429);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame($expected, $e->getRetryAfter());
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function retryInfoDelays(): iterable
    {
        yield 'whole seconds' => ['41s', 41];
        yield 'fraction rounded up' => ['34.4s', 35];
        yield 'below a second' => ['0.25s', 1];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('bodiesWithoutRetryInfo')]
    public function testHasNoRetryTimeWithoutRetryInfoDetail(array $body)
    {
        $response = $this->response(json_encode($body), 429);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function bodiesWithoutRetryInfo(): iterable
    {
        yield 'error without details' => [['error' => ['message' => 'Rate limit reached for requests', 'type' => 'requests']]];
        yield 'details of another type' => [['error' => ['code' => 429, 'message' => 'Quota exceeded.', 'details' => [
            ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaMetric' => 'generativelanguage.googleapis.com/generate_content_free_tier_requests']]],
        ]]]];
    }

    public function testPrefersTheRetryAfterHeaderOverARetryInfoDetail()
    {
        $response = $this->response(json_encode([
            'error' => ['details' => [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '41s']]],
        ]), 429, ['retry-after' => '7']);

        try {
            $this->subject()->throwOnHttpError($response);
            $this->fail('Expected RateLimitExceededException.');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(7, $e->getRetryAfter());
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function response(string $body, int $statusCode, array $headers = [], string $url = 'https://example.test/'): ResponseInterface
    {
        $client = new MockHttpClient([
            new MockResponse($body, [
                'http_code' => $statusCode,
                'response_headers' => $headers,
            ]),
        ]);

        return $client->request('GET', $url);
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
