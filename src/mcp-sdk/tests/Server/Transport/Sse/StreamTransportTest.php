<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Tests\Server\Transport\Sse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Server\Transport\Sse\StoreInterface;
use Symfony\AI\McpSdk\Server\Transport\Sse\StreamTransport;
use Symfony\Component\Uid\Uuid;

#[Small]
#[CoversClass(StreamTransport::class)]
class StreamTransportTest extends TestCase
{
    public static int $connectionAborted = 0;

    protected function tearDown(): void
    {
        parent::tearDown();
        self::$connectionAborted = 0;
    }

    public function testInitializeEmitsEndpointEvent()
    {
        $endpoint = 'https://example.test/mcp/messages';
        $transport = new StreamTransport($endpoint, $this->createMock(StoreInterface::class), Uuid::v7());
        $actual = $this->capture(fn () => $transport->initialize());

        $this->assertSame('event: endpoint'.\PHP_EOL.'data: '.$endpoint.\PHP_EOL.\PHP_EOL, $actual);
    }

    public function testReceiveYieldsSingleMessageFromStore()
    {
        $id = Uuid::v7();
        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('pop')
            ->with($id)
            ->willReturn('hello');

        $transport = new StreamTransport('x', $store, $id);
        $generator = $transport->receive();

        $this->assertInstanceOf(\Generator::class, $generator);
        $this->assertSame('hello', $generator->current());
        $generator->next();
        $this->assertFalse($generator->valid());
    }

    public function testSendEmitsMessageEventAndFlushes()
    {
        $transport = new StreamTransport('x', $this->createMock(StoreInterface::class), Uuid::v7());
        $actual = $this->capture(fn () => $transport->send('payload'));

        $this->assertSame('event: message'.\PHP_EOL.'data: payload'.\PHP_EOL.\PHP_EOL, $actual);
    }

    public function testMultipleSendsProduceMultipleResponses()
    {
        $transport = new StreamTransport('x', $this->createMock(StoreInterface::class), Uuid::v7());
        $first = $this->capture(fn () => $transport->send('one'));
        $second = $this->capture(fn () => $transport->send('two'));

        $this->assertSame([
            'event: message'.\PHP_EOL.'data: one'.\PHP_EOL.\PHP_EOL,
            'event: message'.\PHP_EOL.'data: two'.\PHP_EOL.\PHP_EOL,
        ], [$first, $second]);
    }

    public function testCloseRemovesSessionFromStore()
    {
        $id = Uuid::v7();
        $store = $this->createMock(StoreInterface::class);
        $store->expects($this->once())
            ->method('remove')
            ->with($id);

        $transport = new StreamTransport('x', $store, $id);
        $transport->close();
    }

    public function testIsConnectedRespectsConnectionAbortedPolyfill()
    {
        $transport = new StreamTransport('x', $this->createMock(StoreInterface::class), Uuid::v7());

        self::$connectionAborted = 0;
        $this->assertTrue($transport->isConnected());

        self::$connectionAborted = 1;
        $this->assertFalse($transport->isConnected());
    }

    private function capture(callable $fn): string
    {
        $buffer = '';
        ob_start(function (string $chunk) use (&$buffer) {
            $buffer .= $chunk;

            return '';
        });

        $fn();

        ob_end_flush();

        return $buffer;
    }
}
