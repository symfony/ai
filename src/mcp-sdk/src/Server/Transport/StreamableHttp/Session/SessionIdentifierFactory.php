<?php

namespace Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session;

use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionIdentifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\UuidV4;

readonly class SessionIdentifierFactory
{
    public function __construct(private ?Security $security = null) {}

    public function get(?UuidV4 $id = null):SessionIdentifier
    {
        return new SessionIdentifier($id ?? new UuidV4(), $this->security?->getUser()?->getUserIdentifier());
    }
}
