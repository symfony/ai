<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Model;

/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ServiceDefinition
{
    /**
     * @param ?class-string                    $class
     * @param ?string                          $alias       if this has a value, it is the "real" definition's id
     * @param string[]                         $calls
     * @param ServiceTag[]                     $tags
     * @param array{0: string|null, 1: string} $constructor
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $class,
        private readonly ?string $alias,
        private readonly array $calls,
        private readonly array $tags,
        private readonly array $constructor,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return ?class-string
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @return string[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return ServiceTag[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    public function getConstructor(): array
    {
        return $this->constructor;
    }
}
