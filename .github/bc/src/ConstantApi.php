<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConstantApi
{
    /**
     * @param 'public'|'protected'|'private' $visibility
     */
    public function __construct(
        public readonly string $name,
        public readonly string $visibility,
        public readonly bool $final,
        public readonly bool $internal,
        public readonly ?string $deprecated,
        public readonly int $line,
        public readonly ?string $value = null,
    ) {
    }
}
