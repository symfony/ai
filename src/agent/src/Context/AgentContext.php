<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\UpdateInterface;

/**
 * Handle given to a {@see ContextProcessorInterface} during a run. It exposes
 * the running agent and lets a processor emit progress updates that the runner
 * forwards to the consumer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentContext
{
    /**
     * @var list<UpdateInterface>
     */
    private array $updates = [];

    public function __construct(
        private readonly AgentInterface $agent,
    ) {
    }

    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    public function notify(UpdateInterface $update): void
    {
        $this->updates[] = $update;
    }

    public function reportProgress(string $stage, string $message = '', mixed $payload = null): void
    {
        $this->updates[] = new Progress($stage, $message, $payload);
    }

    /**
     * Returns and clears the buffered updates. Called by the runner.
     *
     * @return list<UpdateInterface>
     */
    public function flushUpdates(): array
    {
        $updates = $this->updates;
        $this->updates = [];

        return $updates;
    }
}
