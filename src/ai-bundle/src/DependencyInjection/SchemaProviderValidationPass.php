<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\DependencyInjection;

use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Validates that every service ID referenced by `provider` on a `#[Schema]` attribute
 * attached to a tagged tool's invocable method is registered as a schema provider.
 *
 * Only tool parameters are checked: structured-output DTOs are not container-tagged,
 * so the same guarantee can't be enforced at build time for them.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
final class SchemaProviderValidationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $providers = $container->findTaggedServiceIds('ai.platform.json_schema.provider');

        foreach ($container->findTaggedServiceIds('ai.tool') as $serviceId => $tags) {
            $class = $container->getDefinition($serviceId)->getClass();

            if (null === $class || !class_exists($class)) {
                continue;
            }

            foreach ($tags as $tag) {
                $method = $tag['method'] ?? '__invoke';

                if (!method_exists($class, $method)) {
                    continue;
                }

                $reflection = new \ReflectionMethod($class, $method);

                foreach ($reflection->getParameters() as $parameter) {
                    foreach ($parameter->getAttributes(Schema::class) as $attribute) {
                        $providerId = $attribute->newInstance()->provider;

                        if (null === $providerId) {
                            continue;
                        }

                        if (!isset($providers[$providerId])) {
                            throw new InvalidArgumentException(\sprintf('Tool "%s" (%s::%s()) references schema provider "%s" on parameter "$%s", but no service with that id is tagged "ai.platform.json_schema.provider".', $serviceId, $class, $method, $providerId, $parameter->getName()));
                        }
                    }
                }
            }
        }
    }
}
