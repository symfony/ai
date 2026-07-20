<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

/**
 * Pauses execution; abstracted so retry backoff delays can be controlled in tests.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface SleeperInterface
{
    /**
     * @param non-negative-int $milliseconds
     */
    public function sleep(int $milliseconds): void;
}
