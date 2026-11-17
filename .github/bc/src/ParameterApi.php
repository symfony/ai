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
final class ParameterApi
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public readonly ?string $default,
        public readonly bool $variadic,
        public readonly bool $byRef,
    ) {
    }

    public function isOptional(): bool
    {
        return null !== $this->default || $this->variadic;
    }
}
