<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CancellationTest extends TestCase
{
    public function testRequestCancelsTheActiveResponseOnce()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $cancellation = new Cancellation();
        $cancellation->activate(new RawHttpResult($response));

        $this->assertFalse($cancellation->isRequested());

        $cancellation->request();
        $cancellation->request();

        $this->assertTrue($cancellation->isRequested());
    }

    public function testActivateCancelsImmediatelyWhenAlreadyRequested()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('cancel');

        $cancellation = new Cancellation();
        $cancellation->request();
        $cancellation->activate(new RawHttpResult($response));
    }

    public function testActivateIgnoresNonHttpRawResults()
    {
        $cancellation = new Cancellation();
        $cancellation->activate(new InMemoryRawResult());
        $cancellation->request();

        $this->assertTrue($cancellation->isRequested());
    }

    public function testDeactivateReleasesTheActiveResponse()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->never())->method('cancel');

        $cancellation = new Cancellation();
        $cancellation->activate(new RawHttpResult($response));
        $cancellation->deactivate();
        $cancellation->request();
    }

    public function testRequestCancelsTheForwardedExecution()
    {
        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('Done'));
        });

        $cancellation = new Cancellation();

        $this->assertSame($execution, $cancellation->forward($execution));

        $cancellation->request();

        $this->assertSame([], iterator_to_array($execution, false));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The agent execution was canceled.');

        $execution->getResult();
    }

    public function testForwardCancelsImmediatelyWhenAlreadyRequested()
    {
        $cancellation = new Cancellation();
        $cancellation->request();

        $execution = new Execution(static function (): \Generator {
            yield new ResultUpdate(new TextResult('Done'));
        });
        $cancellation->forward($execution);

        $this->assertSame([], iterator_to_array($execution, false));
    }
}
