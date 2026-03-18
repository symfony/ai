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

    public function testWriteAndReadRoundTrip(): void
    {
        $id = Uuid::v4();
        $store = new FrameworkSessionStore($this->createInMemoryHandler(), self::PREFIX);

        self::assertTrue($store->write($id, 'session-data'));
        self::assertSame('session-data', $store->read($id));
    }

    public function testReadReturnsFalseForEmptyString(): void
    {
        $handler = self::createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn('');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertFalse($store->read(Uuid::v4()));
    }

    public function testReadReturnsFalseForInvalidEnvelope(): void
    {
        $handler = self::createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn('not-json');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertFalse($store->read(Uuid::v4()));
    }

    public function testReadReturnsFalseAndDestroysExpiredSession(): void
    {
        $id = Uuid::v4();
        $expired = json_encode(['d' => 'old-data', 'e' => time() - 1]);

        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn($expired);
        $handler->expects(self::once())->method('destroy')->with(self::PREFIX.$id);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertFalse($store->read($id));
    }

    public function testDestroyDelegatesToHandler(): void
    {
        $id = Uuid::v4();
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())
            ->method('destroy')
            ->with(self::PREFIX.$id)
            ->willReturn(true);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertTrue($store->destroy($id));
    }

    public function testExistsReturnsTrueForValidSession(): void
    {
        $envelope = json_encode(['d' => 'data', 'e' => time() + 3600]);

        $handler = self::createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn($envelope);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertTrue($store->exists(Uuid::v4()));
    }

    public function testExistsReturnsFalseForMissingSession(): void
    {
        $handler = self::createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn('');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertFalse($store->exists(Uuid::v4()));
    }

    public function testExistsReturnsFalseForExpiredSession(): void
    {
        $expired = json_encode(['d' => 'data', 'e' => time() - 1]);

        $handler = self::createStub(\SessionHandlerInterface::class);
        $handler->method('read')->willReturn($expired);

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertFalse($store->exists(Uuid::v4()));
    }

    public function testGcReturnsEmptyArray(): void
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::never())->method('gc');

        $store = new FrameworkSessionStore($handler, self::PREFIX);

        self::assertSame([], $store->gc());
    }

    public function testCustomPrefix(): void
    {
        $id = Uuid::v4();
        $envelope = json_encode(['d' => 'data', 'e' => time() + 3600]);

        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())
            ->method('read')
            ->with('custom_'.$id)
            ->willReturn($envelope);

        $store = new FrameworkSessionStore($handler, 'custom_');

        self::assertSame('data', $store->read($id));
    }

    public function testTtlIsRespected(): void
    {
        $id = Uuid::v4();

        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())
            ->method('write')
            ->with(self::PREFIX.$id, self::callback(static function (string $raw): bool {
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
