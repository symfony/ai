<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Step;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Step implements StepInterface
{
    public function __construct(
        private readonly string $name,
        private readonly \Closure $executor,
        private readonly bool $parallel = false,
        private readonly int $retryCount = 3,
        private readonly int $retryDelay = 1,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function execute(AgentInterface $agent, WorkflowStateInterface $state): ResultInterface
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryCount) {
            try {
                return ($this->executor)($agent, $state);
            } catch (\Throwable $e) {
                $lastException = $e;
                ++$attempt;

                if ($attempt < $this->retryCount) {
                    $this->clock->sleep($this->retryDelay);
                }
            }
        }

        throw new RuntimeException(\sprintf('Step "%s" failed after %d attempts', $this->name, $this->retryCount), previous: $lastException);
    }

    public function isParallel(): bool
    {
        return $this->parallel;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }
}
