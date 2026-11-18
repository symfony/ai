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
final class ClassApi
{
    /**
     * @param 'class'|'interface'|'trait'|'enum' $kind
     * @param list<string>                       $interfaces
     * @param list<string>                       $traits
     * @param array<string, ConstantApi>         $constants  keyed by name
     * @param array<string, PropertyApi>         $properties keyed by name
     * @param array<string, MethodApi>           $methods    keyed by lowercase name
     * @param array<string, string>              $methodTags @method signatures, keyed by lowercase name
     * @param list<string>                       $cases
     */
    public function __construct(
        public readonly string $name,
        public readonly string $kind,
        public readonly string $package,
        public readonly string $file,
        public readonly int $line,
        public readonly bool $final,
        public readonly bool $abstract,
        public readonly bool $readonly,
        public readonly ?string $finalTag,
        public readonly bool $internal,
        public readonly bool $experimental,
        public readonly ?string $deprecated,
        public readonly bool $attribute,
        public readonly ?string $parent,
        public readonly array $interfaces,
        public readonly array $traits,
        public readonly array $constants,
        public readonly array $properties,
        public readonly array $methods,
        public readonly array $methodTags,
        public readonly array $cases,
        public readonly bool $announcedInternal = false,
    ) {
    }

    /**
     * Final by the native keyword or a plain @final tag; "@final since X" only announces it.
     */
    public function isFinal(): bool
    {
        return $this->final || '' === $this->finalTag || 'enum' === $this->kind;
    }

    /**
     * Whether users can implement, extend or use it, and so depend on its protected members.
     */
    public function isExtensible(): bool
    {
        return 'class' !== $this->kind ? 'enum' !== $this->kind : !$this->isFinal();
    }

    public function isCovered(): bool
    {
        return !$this->internal && !$this->experimental;
    }
}
