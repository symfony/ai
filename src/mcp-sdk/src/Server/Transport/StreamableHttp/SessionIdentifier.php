<?php

namespace Symfony\AI\McpSdk\Server\Transport\StreamableHttp;

use Symfony\Component\Uid\Uuid;

readonly final class SessionIdentifier
{
    /**
     * @param Uuid $sessionId
     * @param string|null $userIdentifier A unique identifier for the current logged-in user, if applicable
     */
    public function __construct(public Uuid $sessionId, public ?string $userIdentifier = null) { }

    public function __toString(): string
    {
        return $this->sessionId->toRfc4122() . ($this->userIdentifier ? '_' . $this->userIdentifier : '');
    }
}
