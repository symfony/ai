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
final class MethodApi
{
    /**
     * @param 'public'|'protected'|'private' $visibility
     * @param list<ParameterApi>             $parameters
     * @param array<string, string|null>     $virtualParameters type by name, documented with @param only
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly string $visibility,
        public readonly bool $static,
        public readonly bool $abstract,
        public readonly bool $final,
        public readonly ?string $finalTag,
        public readonly bool $internal,
        public readonly ?string $deprecated,
        public readonly array $parameters,
        public readonly array $virtualParameters,
        public readonly ?string $returnType,
        public readonly int $line,
        public readonly bool $announcedInternal = false,
    ) {
    }

    public function isFinal(): bool
    {
        return $this->final || '' === $this->finalTag || 'private' === $this->visibility;
    }

    public function isConstructor(): bool
    {
        return '__construct' === strtolower($this->name);
    }
}
