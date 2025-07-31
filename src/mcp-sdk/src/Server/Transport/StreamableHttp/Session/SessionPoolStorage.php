<?php

namespace Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\AI\McpSdk\Exception\InvalidSessionIdException;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionIdentifier;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionStorageInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

readonly class SessionPoolStorage implements SessionStorageInterface
{
    public function __construct(private CacheItemPoolInterface $cachePool, private int $ttlInSeconds = 60 * 60) { }
    public function exists(SessionIdentifier $sessionIdentifier): bool
    {
        try {
            return $this->cachePool->hasItem($this->getCacheKey($sessionIdentifier));
        } catch(InvalidArgumentException) {
            throw new InvalidSessionIdException(sprintf('Session identifier (id: "%s", user: "%s" is invalid)', $sessionIdentifier->sessionId, $sessionIdentifier->userIdentifier ?? ''));
        }
    }

    public function save(SessionIdentifier $sessionIdentifier, Session $session): void
    {
        try {
            $item = $this->cachePool->getItem($this->getCacheKey($sessionIdentifier));
            $item->set($session->getData());
            $item->expiresAfter($this->ttlInSeconds);
        } catch(InvalidArgumentException) {
            throw new InvalidSessionIdException(sprintf('Session identifier (id: "%s", user: "%s" is invalid)', $sessionIdentifier->sessionId, $sessionIdentifier->userIdentifier ?? ''));
        }
        $this->cachePool->save($item);
    }

    public function remove(SessionIdentifier $sessionIdentifier): void
    {
        try {
            $this->cachePool->deleteItem($this->getCacheKey($sessionIdentifier));
        } catch(InvalidArgumentException) {
            throw new InvalidSessionIdException(sprintf('Session identifier (id: "%s", user: "%s" is invalid)', $sessionIdentifier->sessionId, $sessionIdentifier->userIdentifier ?? ''));
        }
    }

    private function getCacheKey(SessionIdentifier $sessionIdentifier): string
    {
        return sprintf('session_%s_%s', $sessionIdentifier->sessionId->toRfc4122(), $sessionIdentifier->userIdentifier ?? '');
    }

    public function get(SessionIdentifier $sessionIdentifier): Session
    {
        try {
            $item = $this->cachePool->getItem($this->getCacheKey($sessionIdentifier));
            if (!$item->isHit()) {
                throw new InvalidSessionIdException(sprintf('Session identifier (id: "%s", user: "%s" is invalid)', $sessionIdentifier->sessionId, $sessionIdentifier->userIdentifier ?? ''));
            }
            $item->expiresAfter($this->ttlInSeconds);
            return new Session($sessionIdentifier, $this, $item->get());
        } catch(InvalidArgumentException) {
            throw new InvalidSessionIdException(sprintf('Session identifier (id: "%s", user: "%s" is invalid)', $sessionIdentifier->sessionId, $sessionIdentifier->userIdentifier ?? ''));
        }
    }
}
