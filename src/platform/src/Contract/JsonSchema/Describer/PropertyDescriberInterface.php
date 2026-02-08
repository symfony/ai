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
use Symfony\AI\Platform\Contract\JsonSchema\Property;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
interface PropertyDescriberInterface
{
    /**
     * @param JsonSchema|array<mixed>|null $schema
     *
     * @param-out JsonSchema|array<mixed>|null $schema
     */
    public function describeProperty(Property $property, ?array &$schema): void;
}
