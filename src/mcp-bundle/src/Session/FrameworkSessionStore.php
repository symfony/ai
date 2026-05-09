<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Session;

use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Simon Chrzanowski
 */
final class FrameworkSessionStore implements SessionStoreInterface
{
    public function __construct(
        private readonly \SessionHandlerInterface $handler,
        private readonly string $prefix = 'mcp-',
        private readonly int $ttl = 3600,
    ) {
    }

    public function exists(Uuid $id): bool
    {
        return false !== $this->read($id);
    }

    public function read(Uuid $id): string|false
    {
        $raw = $this->handler->read($this->getKey($id));
        if ('' === $raw || false === $raw) {
            return false;
        }

        $envelope = json_decode($raw, true);
        if (!\is_array($envelope) || !isset($envelope['d'], $envelope['e'])) {
            return false;
        }

        if ($envelope['e'] < time()) {
            $this->destroy($id);

            return false;
        }

        return $envelope['d'];
    }

    public function write(Uuid $id, string $data): bool
    {
        $envelope = json_encode(['d' => $data, 'e' => time() + $this->ttl], \JSON_THROW_ON_ERROR);

        return $this->handler->write($this->getKey($id), $envelope);
    }

    public function destroy(Uuid $id): bool
    {
        return $this->handler->destroy($this->getKey($id));
    }

    public function gc(): array
    {
        // Expiry is handled lazily on read() — expired sessions are destroyed
        // when accessed. We cannot call SessionHandlerInterface::gc() because
        // it would affect all sessions (including framework HTTP sessions)
        // and returns int|false, not the Uuid[] required by this interface.
        return [];
    }

    private function getKey(Uuid $id): string
    {
        return $this->prefix.$id;
    }
}
