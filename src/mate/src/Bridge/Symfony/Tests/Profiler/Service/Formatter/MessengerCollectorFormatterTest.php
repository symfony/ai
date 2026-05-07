<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\MessengerCollectorFormatter;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MessengerCollectorFormatterTest extends TestCase
{
    private MessengerCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MessengerCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('messenger', $this->formatter->getName());
    }

    public function testFormatWithNoMessages()
    {
        $collector = $this->createMock(MessengerDataCollector::class);
        $collector->method('getBuses')->willReturn(['messenger.default_bus']);
        $collector->method('getMessages')->with(null)->willReturn([]);
        $collector->method('getExceptionsCount')->with(null)->willReturn(0);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['bus_count']);
        $this->assertSame(['messenger.default_bus'], $result['buses']);
        $this->assertSame(0, $result['message_count']);
        $this->assertSame(0, $result['exception_count']);
        $this->assertSame([], $result['messages']);
        $this->assertFalse($result['messages_truncated']);
    }

    public function testFormatSingleMessage()
    {
        $messageData = [
            'bus' => 'messenger.default_bus',
            'caller' => ['name' => 'MyController::dispatch', 'file' => '/app/Controller.php', 'line' => 42],
            'message' => ['type' => 'App\\Message\\SendEmail', 'value' => 'HIDDEN'],
            'stamps' => [
                'Symfony\\Component\\Messenger\\Stamp\\DelayStamp' => [['delay' => 5000]],
            ],
            'exception' => null,
        ];

        $collector = $this->createMock(MessengerDataCollector::class);
        $collector->method('getBuses')->willReturn(['messenger.default_bus']);
        $collector->method('getMessages')->with(null)->willReturn([$messageData]);
        $collector->method('getExceptionsCount')->with(null)->willReturn(0);

        $result = $this->formatter->format($collector);

        $this->assertCount(1, $result['messages']);
        $msg = $result['messages'][0];

        $this->assertSame('messenger.default_bus', $msg['bus']);
        $this->assertSame('App\\Message\\SendEmail', $msg['message_type']);
        $this->assertSame('MyController::dispatch', $msg['caller_name']);
        $this->assertSame('/app/Controller.php', $msg['caller_file']);
        $this->assertSame(42, $msg['caller_line']);
        $this->assertSame(1, $msg['stamp_count']);
        $this->assertSame(['Symfony\\Component\\Messenger\\Stamp\\DelayStamp'], $msg['stamps']);
        $this->assertFalse($msg['has_exception']);
        $this->assertNull($msg['exception_type']);
        $this->assertArrayNotHasKey('value', $msg);
    }

    public function testFormatMessageWithException()
    {
        $messageData = [
            'bus' => 'messenger.default_bus',
            'caller' => ['name' => 'App\\Handler\\Handler::__invoke', 'file' => '/app/Handler.php', 'line' => 10],
            'message' => ['type' => 'App\\Message\\FailingMessage', 'value' => 'HIDDEN'],
            'stamps' => [],
            'exception' => ['type' => 'RuntimeException', 'message' => 'Something failed'],
        ];

        $collector = $this->createMock(MessengerDataCollector::class);
        $collector->method('getBuses')->willReturn(['messenger.default_bus']);
        $collector->method('getMessages')->with(null)->willReturn([$messageData]);
        $collector->method('getExceptionsCount')->with(null)->willReturn(1);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['exception_count']);
        $msg = $result['messages'][0];
        $this->assertTrue($msg['has_exception']);
        $this->assertSame('RuntimeException', $msg['exception_type']);
    }

    public function testFormatHandlesDataObjects()
    {
        $messageType = new class {
            public function __toString(): string
            {
                return 'App\\Message\\WrappedMessage';
            }
        };

        $messageEntry = $this->createMock(Data::class);
        $messageEntry->method('getValue')->with(true)->willReturn([
            'type' => $messageType,
            'value' => 'HIDDEN',
        ]);

        $stampsData = $this->createMock(Data::class);
        $stampsData->method('getValue')->with(true)->willReturn([
            'Symfony\\Component\\Messenger\\Stamp\\HandledStamp' => [],
        ]);

        $messageData = $this->createMock(Data::class);
        $messageData->method('getValue')->with(true)->willReturn([
            'bus' => 'messenger.default_bus',
            'caller' => ['name' => 'TestCaller', 'file' => '/test.php', 'line' => 1],
            'message' => $messageEntry,
            'stamps' => $stampsData,
            'exception' => null,
        ]);

        $collector = $this->createMock(MessengerDataCollector::class);
        $collector->method('getBuses')->willReturn(['messenger.default_bus']);
        $collector->method('getMessages')->with(null)->willReturn([$messageData]);
        $collector->method('getExceptionsCount')->with(null)->willReturn(0);

        $result = $this->formatter->format($collector);

        $this->assertCount(1, $result['messages']);
        $msg = $result['messages'][0];
        $this->assertSame('App\\Message\\WrappedMessage', $msg['message_type']);
        $this->assertSame(['Symfony\\Component\\Messenger\\Stamp\\HandledStamp'], $msg['stamps']);
    }

    public function testFormatTruncatesAt50Messages()
    {
        $messages = [];
        for ($i = 0; $i < 51; ++$i) {
            $messages[] = [
                'bus' => 'messenger.default_bus',
                'caller' => ['name' => 'caller', 'file' => '/f.php', 'line' => 1],
                'message' => ['type' => 'App\\Message\\Msg', 'value' => 'HIDDEN'],
                'stamps' => [],
                'exception' => null,
            ];
        }

        $collector = $this->createMock(MessengerDataCollector::class);
        $collector->method('getBuses')->willReturn(['messenger.default_bus']);
        $collector->method('getMessages')->with(null)->willReturn($messages);
        $collector->method('getExceptionsCount')->with(null)->willReturn(0);

        $result = $this->formatter->format($collector);

        $this->assertCount(50, $result['messages']);
        $this->assertTrue($result['messages_truncated']);
    }

    public function testFormatDoesNotExposeMessageValue()
    {
        $messageData = [
            'bus' => 'messenger.default_bus',
            'caller' => ['name' => 'caller', 'file' => '/f.php', 'line' => 1],
            'message' => ['type' => 'App\\Message\\Msg', 'value' => 'SECRET_DTO_CONTENT'],
            'stamps' => [],
            'exception' => null,
        ];

        $collector = $this->createMock(MessengerDataCollector::class);
        $collector->method('getBuses')->willReturn(['messenger.default_bus']);
        $collector->method('getMessages')->with(null)->willReturn([$messageData]);
        $collector->method('getExceptionsCount')->with(null)->willReturn(0);

        $result = $this->formatter->format($collector);

        $msg = $result['messages'][0];
        $this->assertArrayNotHasKey('value', $msg);
        $encoded = json_encode($result);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('SECRET_DTO_CONTENT', $encoded);
    }

    public function testGetSummary()
    {
        $collector = $this->createMock(MessengerDataCollector::class);
        $collector->method('getBuses')->willReturn(['messenger.default_bus', 'messenger.async_bus']);
        $collector->method('getMessages')->with(null)->willReturn([[], []]);
        $collector->method('getExceptionsCount')->with(null)->willReturn(1);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(['messenger.default_bus', 'messenger.async_bus'], $result['buses']);
        $this->assertSame(2, $result['message_count']);
        $this->assertSame(1, $result['exception_count']);
        $this->assertArrayNotHasKey('messages', $result);
        $this->assertArrayNotHasKey('bus_count', $result);
    }
}
