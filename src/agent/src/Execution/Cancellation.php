<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class Cancellation
{
    private bool $requested = false;

    private ?\Closure $onCancel = null;

    public function request(): void
    {
        if ($this->requested) {
            return;
        }

        $this->requested = true;

        if (null !== $this->onCancel) {
            ($this->onCancel)();
        }
    }

    public function activate(RawResultInterface $result): void
    {
        if (!$result instanceof RawHttpResult) {
            return;
        }

        $this->watch($result->getObject()->cancel(...));
    }

    /**
     * Forwards a cancellation request to the execution of a delegated agent.
     */
    public function forward(Execution $execution): Execution
    {
        $this->watch($execution->cancel(...));

        return $execution;
    }

    public function deactivate(): void
    {
        $this->onCancel = null;
    }

    public function isRequested(): bool
    {
        return $this->requested;
    }

    private function watch(\Closure $onCancel): void
    {
        $this->onCancel = $onCancel;

        if ($this->requested) {
            $onCancel();
        }
    }
}
