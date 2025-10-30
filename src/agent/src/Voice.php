<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class Voice
{
    public function __construct(
        private string $input,
        private string $provider,
    ) {
    }
}
