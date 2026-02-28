<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema;

use Symfony\AI\Platform\Contract\JsonSchema\Describer\DescriberInterface;

/**
 * Implement this in {@see DescriberInterface} to inject {@see Factory} to recursively build schema.
 */
interface FactoryAwareInterface
{
    public function setFactory(Factory $factory): void;
}
