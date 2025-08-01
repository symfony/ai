<?php

namespace Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session;

use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionIdentifier;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionStorageInterface;

class SessionFactory
{
    public function __construct(private readonly SessionIdentifierFactory $identifierFactory, private readonly SessionStorageInterface $storage)
    {
    }

    public function get(?SessionIdentifier $sessionIdentifier = null): Session
    {
        return new Session($sessionIdentifier ?? $this->identifierFactory->get(), $this->storage);
    }
}
