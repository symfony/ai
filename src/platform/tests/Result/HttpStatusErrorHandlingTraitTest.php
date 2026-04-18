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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HttpStatusErrorHandlingTraitTest extends TestCase
{
    public function testNoopOnSuccessfulStatusCodes()
    {
        $subject = $this->subject();

        foreach ([200, 201, 204, 301, 302, 500, 503] as $status) {
            $response = $this->response('', $status);
            $subject->throwOnHttpError($response);
            $this->assertTrue(true, \sprintf('No exception thrown for status %d.', $status));
        }
    }

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