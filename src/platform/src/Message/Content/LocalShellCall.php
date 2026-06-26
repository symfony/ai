<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class LocalShellCall implements ContentInterface
{
    /**
     * @param list<string> $command
     */
    public function __construct(
        private readonly array $command = [],
        private readonly ?string $callId = null,
        private readonly ?string $id = null,
        private readonly ?string $status = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function getCallId(): ?string
    {
        return $this->callId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
