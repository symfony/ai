<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Message\Request;

final class FactoryTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Factory();
    }

    public function testCreateRequest(): void
    {
        $json = '{"jsonrpc": "2.0", "method": "test_method", "params": {"foo": "bar"}, "id": 123}';

        $result = $this->factory->create($json);

        self::assertInstanceOf(Request::class, $result);
        self::assertSame('test_method', $result->method);
        self::assertSame(['foo' => 'bar'], $result->params);
        self::assertSame(123, $result->id);
    }

    public function testCreateNotification(): void
    {
        $json = '{"jsonrpc": "2.0", "method": "notifications/test_event", "params": {"foo": "bar"}}';

        $result = $this->factory->create($json);

        self::assertInstanceOf(Notification::class, $result);
        self::assertSame('notifications/test_event', $result->method);
        self::assertSame(['foo' => 'bar'], $result->params);
    }

    public function testInvalidJson(): void
    {
        $this->expectException(\JsonException::class);

        $this->factory->create('invalid json');
    }

    public function testMissingMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON-RPC request, missing method');

        $this->factory->create('{"jsonrpc": "2.0", "params": {}, "id": 1}');
    }
}
