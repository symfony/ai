<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Enricher;

/**
 * Refines the schema of a tool parameter, as the component's property schema enrichers only see class properties.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ParameterSchemaEnricherInterface
{
    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function enrichParameter(\ReflectionParameter $parameter, array $schema): array;
}
