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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RetryableExceptionInterface;
use Symfony\AI\Platform\Exception\ServerException;

final class RetryableExceptionInterfaceTest extends TestCase
{
    public function testServerExceptionIsRetryable()
    {
        $this->assertInstanceOf(RetryableExceptionInterface::class, new ServerException(503));
    }

    public function testRateLimitExceededExceptionIsRetryable()
    {
        $this->assertInstanceOf(RetryableExceptionInterface::class, new RateLimitExceededException(30));
    }

    public function testARejectedRequestIsNotRetryable()
    {
        $this->assertNotInstanceOf(RetryableExceptionInterface::class, new BadRequestException('bad request'));
    }

    public function testAnAuthenticationFailureIsNotRetryable()
    {
        $this->assertNotInstanceOf(RetryableExceptionInterface::class, new AuthenticationException('unauthorized'));
    }

    public function testFilteredContentIsNotRetryable()
    {
        $this->assertNotInstanceOf(RetryableExceptionInterface::class, new ContentFilterException('filtered'));
    }
}
