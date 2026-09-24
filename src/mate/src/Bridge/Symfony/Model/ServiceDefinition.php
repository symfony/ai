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
 * @phpstan-type ParsedArgument array{key: string|null, type: 'service'|'collection'|'scalar', value: mixed, literal: bool}
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
     * @param list<ParsedArgument>             $arguments   positional constructor/factory arguments, still unnamed and
     *                                                      unredacted; ServiceArgumentResolver turns them into output
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $class,
        private readonly ?string $alias,
        private readonly array $calls,
        private readonly array $tags,
        private readonly array $constructor,
        private readonly array $arguments = [],
        private readonly bool $public = false,
        private readonly bool $synthetic = false,
        private readonly bool $lazy = false,
        private readonly bool $shared = true,
        private readonly bool $abstract = false,
        private readonly bool $autowired = false,
        private readonly bool $autoconfigured = false,
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

    /**
     * Positional, unnamed and unredacted. Pass through
     * {@see \Symfony\AI\Mate\Bridge\Symfony\Service\ServiceArgumentResolver} first.
     *
     * @return list<ParsedArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function isSynthetic(): bool
    {
        return $this->synthetic;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
    }

    public function isShared(): bool
    {
        return $this->shared;
    }

    public function isAbstract(): bool
    {
        return $this->abstract;
    }

    public function isAutowired(): bool
    {
        return $this->autowired;
    }

    public function isAutoconfigured(): bool
    {
        return $this->autoconfigured;
    }
}
