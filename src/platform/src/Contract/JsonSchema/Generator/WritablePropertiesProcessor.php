<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Generator;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;

/**
 * Keeps the properties a model can fill in, as its output is denormalized into the class.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class WritablePropertiesProcessor implements DefinitionProcessorInterface
{
    public function process(array $definition, string $class, Configuration $config, ?DefinitionParent $parent): array
    {
        foreach ($definition['properties'] ?? [] as $name => $schema) {
            if (true === ($schema['readOnly'] ?? false)) {
                unset($definition['properties'][$name]);
                $definition['required'] = array_values(array_diff($definition['required'] ?? [], [$name]));

                continue;
            }

            unset($definition['properties'][$name]['writeOnly']);
        }

        if ([] === ($definition['required'] ?? null)) {
            unset($definition['required']);
        }

        return $definition;
    }
}
