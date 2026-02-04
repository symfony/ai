<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Confirmation;

/**
 * A policy that can remember decisions for subsequent tool calls.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface RememberablePolicyInterface extends PolicyInterface
{
    public function remember(string $toolName, PolicyDecision $decision): void;
}
