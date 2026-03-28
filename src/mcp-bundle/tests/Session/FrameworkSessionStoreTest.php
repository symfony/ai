<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Session;

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Session\FrameworkSessionStore;
use Symfony\Component\Uid\Uuid;

final class FrameworkSessionStoreTest extends TestCase
{
    private const PREFIX = 'mcp-';

    public function testWriteAndReadRoundTrip()
    {
        $id = Uuid::v4();
        $store = new FrameworkSessionStore($this->createInMemoryHandler(), self::PREFIX);

        $this->assertTrue($store->write($id, 'session-data'));
        $this->assertSame('session-data', $store->read($id));
    }

    public function testReadReturnsFalseForEmptyString()
    {
        $handler = $this->createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn('');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertFalse($store->read(Uuid::v4()));
    }

    public function testReadReturnsFalseForInvalidEnvelope()
    {
        $handler = $this->createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn('not-json');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertFalse($store->read(Uuid::v4()));
    }

    public function testReadReturnsFalseAndDestroysExpiredSession()
    {
        $id = Uuid::v4();
        $expired = json_encode(['d' => 'old-data', 'e' => time() - 1]);

        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn($expired);
        $handler->expects($this->once())->method('destroy')->with(self::PREFIX.$id);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertFalse($store->read($id));
    }

    public function testDestroyDelegatesToHandler()
    {
        $id = Uuid::v4();
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('destroy')
            ->with(self::PREFIX.$id)
            ->willReturn(true);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertTrue($store->destroy($id));
    }

    public function testExistsReturnsTrueForValidSession()
    {
        $envelope = json_encode(['d' => 'data', 'e' => time() + 3600]);

        $handler = $this->createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn($envelope);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertTrue($store->exists(Uuid::v4()));
    }

    public function testExistsReturnsFalseForMissingSession()
    {
        $handler = $this->createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn('');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertFalse($store->exists(Uuid::v4()));
    }

    public function testExistsReturnsFalseForExpiredSession()
    {
        $expired = json_encode(['d' => 'data', 'e' => time() - 1]);

        $handler = $this->createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn($expired);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertFalse($store->exists(Uuid::v4()));
    }

    public function testGcReturnsEmptyArray()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects($this->never())->method('gc');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        $this->assertSame([], $store->gc());
    }

    public function testCustomPrefix()
    {
        $id = Uuid::v4();
        $envelope = json_encode(['d' => 'data', 'e' => time() + 3600]);

        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('read')
            ->with('custom_'.$id)
            ->willReturn($envelope);

        $store = new FrameworkSessionStore($handler, 'custom_');

        $this->assertSame('data', $store->read($id));
    }

    public function testTtlIsRespected()
    {
        $id = Uuid::v4();

        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('write')
            ->with(self::PREFIX.$id, $this->callback(static function (string $raw): bool {
                $envelope = json_decode($raw, true);

                return \is_array($envelope) && $envelope['e'] <= time() + 60;
            }))
            ->willReturn(true);

        $store = new FrameworkSessionStore($handler, self::PREFIX, 60);

        $store->write($id, 'data');
    }

    private function createInMemoryHandler(): \SessionHandlerInterface
    {
        return new class implements \SessionHandlerInterface {
            /** @var array<string, string> */
            private array $data = [];

            public function open(string $path, string $name): bool
            {
                return true;
            }

            public function close(): bool
            {
                return true;
            }

            public function read(string $id): string
            {
                return $this->data[$id] ?? '';
            }

            public function write(string $id, string $data): bool
            {
                $this->data[$id] = $data;

                return true;
            }

            public function destroy(string $id): bool
            {
                unset($this->data[$id]);

                return true;
            }

            public function gc(int $max_lifetime): int
            {
                return 0;
            }
        };
    }
}
