<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * Computer-use action requested by the model, to be executed by the client
 * (e.g. the OpenAI Responses `computer_call` output item).
 *
 * @phpstan-type SafetyCheck array{id: string, code?: string|null, message?: string|null}
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ComputerCallResult extends BaseResult
{
    /**
     * @param array<string, mixed> $action
     * @param list<SafetyCheck>    $pendingSafetyChecks
     */
    public function __construct(
        private readonly array $action = [],
        private readonly ?string $callId = null,
        private readonly array $pendingSafetyChecks = [],
        private readonly ?string $id = null,
        private readonly ?string $status = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->action;
    }

    public function getCallId(): ?string
    {
        return $this->callId;
    }

    /**
     * @return list<SafetyCheck>
     */
    public function getPendingSafetyChecks(): array
    {
        return $this->pendingSafetyChecks;
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
