<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class AbstractScorer implements ScorerInterface
{
    private ?string $reason = null;

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
