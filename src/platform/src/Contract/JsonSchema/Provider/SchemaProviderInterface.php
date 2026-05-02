<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Provider;

/**
 * Returns a JSON Schema fragment merged into a parameter/property schema at runtime.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
interface SchemaProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getSchemaFragment(): array;
}
