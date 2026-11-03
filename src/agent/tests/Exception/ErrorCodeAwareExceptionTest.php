<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Exception\ErrorCodeAwareInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;

final class ErrorCodeAwareExceptionTest extends TestCase
{
    #[DataProvider('provideExceptions')]
    public function testExceptionReportsItsCodeAndWhetherToRetry(ErrorCodeAwareInterface $exception, string $code, bool $retryable)
    {
        $this->assertSame($code, $exception->getErrorCode());
        $this->assertSame($retryable, $exception->isRetryable());
    }

    /**
     * @return iterable<string, array{ErrorCodeAwareInterface, string, bool}>
     */
    public static function provideExceptions(): iterable
    {
        $toolCall = new ToolCall('call_1', 'weather');

        yield 'max iterations' => [new MaxIterationsExceededException(5), 'max_iterations_exceeded', false];
        yield 'tool not found for call' => [ToolNotFoundException::notFoundForToolCall($toolCall), 'tool_not_found', false];
        yield 'tool not found for reference' => [ToolNotFoundException::notFoundForReference(new ExecutionReference('Weather', '__invoke')), 'tool_not_found', false];
        yield 'tool execution failed' => [ToolExecutionException::executionFailed($toolCall, new \RuntimeException('Boom')), 'tool_execution_failed', false];
    }
}
