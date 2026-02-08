<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
interface DescriberInterface
{
    /**
     * @template T of object
     *
     * @param \ReflectionProperty|\ReflectionParameter|\ReflectionClass<T> $reflector
     * @param JsonSchema|array<mixed>|null                                 $schema
     *
     * @param-out JsonSchema|array<mixed>|null                             $schema
     */
    public function describe(\ReflectionProperty|\ReflectionParameter|\ReflectionClass $reflector, ?array &$schema): void;
}
