<?php

namespace Symfony\AI\McpSdk\Server\Transport\StreamableHttp;

use Symfony\AI\McpSdk\Exception\InvalidSessionIdException;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\Session;

interface SessionStorageInterface
{
    /**
     * @param SessionIdentifier $sessionIdentifier
     * @return bool
     * @throws InvalidSessionIdException
     */
    public function exists(SessionIdentifier $sessionIdentifier): bool;

    /**
     * @param SessionIdentifier $sessionIdentifier
     * @param Session $session
     * @return void
     * @throws InvalidSessionIdException
     */
    public function save(SessionIdentifier $sessionIdentifier, Session $session): void;

    /**
     * @param SessionIdentifier $sessionIdentifier
     * @return Session
     */
    public function get(SessionIdentifier $sessionIdentifier): Session;

    /**
     * @param SessionIdentifier $sessionIdentifier
     * @return void
     * @throws InvalidSessionIdException
     */
    public function remove(SessionIdentifier $sessionIdentifier): void;
}
