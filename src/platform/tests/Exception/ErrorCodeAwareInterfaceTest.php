<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ErrorCodeAwareInterface;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RetryableExceptionInterface;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;

final class ErrorCodeAwareInterfaceTest extends TestCase
{
    #[DataProvider('provideExceptions')]
    public function testExceptionReportsItsCodeAndWhetherToRetry(ErrorCodeAwareInterface $exception, string $code, bool $retryable)
    {
        $this->assertSame($code, $exception->getErrorCode());
        $this->assertSame($retryable, $exception->isRetryable());
    }

    #[DataProvider('provideExceptions')]
    public function testRetryableMatchesTheRetryableExceptionInterface(ErrorCodeAwareInterface $exception, string $code, bool $retryable)
    {
        $this->assertSame($retryable, $exception instanceof RetryableExceptionInterface);
    }

    /**
     * @return iterable<string, array{ErrorCodeAwareInterface, string, bool}>
     */
    public static function provideExceptions(): iterable
    {
        yield 'rate limit' => [new RateLimitExceededException(30), 'rate_limit_exceeded', true];
        yield 'server error' => [new ServerException(503), 'provider_error', true];
        yield 'authentication' => [new AuthenticationException('Unauthorized'), 'authentication_failed', false];
        yield 'bad request' => [new BadRequestException('Bad Request'), 'bad_request', false];
        yield 'model not found' => [new ModelNotFoundException('Not Found'), 'model_not_found', false];
        yield 'missing model support' => [MissingModelSupportException::forToolCalling(new Model('some-model')), 'model_not_found', false];
        yield 'content filter' => [new ContentFilterException('Filtered'), 'content_filtered', false];
    }
}
