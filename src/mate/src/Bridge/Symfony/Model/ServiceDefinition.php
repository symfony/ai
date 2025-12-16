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
        public string $id,
        public ?string $class,
        public ?string $alias,
        public array $calls,
        public array $tags,
        public array $constructor,
    ) {
    }
}
