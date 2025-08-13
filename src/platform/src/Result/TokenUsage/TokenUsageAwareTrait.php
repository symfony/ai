<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\TokenUsage;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
trait TokenUsageAwareTrait
{
    private ?TokenUsage $tokenUsage = null;

    public function getTokenUsage(): ?TokenUsage
    {
        return $this->tokenUsage;
    }

    public function setTokenUsage(?TokenUsage $tokenUsage): void
    {
        $this->tokenUsage = $tokenUsage;
    }
}
