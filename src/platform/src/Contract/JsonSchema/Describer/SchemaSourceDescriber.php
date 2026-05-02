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

use Psr\Container\ContainerInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Camille Islasse <guiziweb@gmail.com>
 */
final class SchemaSourceDescriber implements PropertyDescriberInterface
{
    public function __construct(
        private readonly ContainerInterface $providers,
    ) {
    }

    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        foreach ($subject->getAttributes(SchemaSource::class) as $attribute) {
            if (!$this->providers->has($attribute->provider)) {
                throw new RuntimeException(\sprintf('SchemaSource "%s" is not registered. Make sure the class implements "%s" and is autoconfigured as a service.', $attribute->provider, SchemaProviderInterface::class));
            }

            $provider = $this->providers->get($attribute->provider);

            if (!$provider instanceof SchemaProviderInterface) {
                throw new RuntimeException(\sprintf('SchemaSource service for "%s" must implement "%s", got "%s".', $attribute->provider, SchemaProviderInterface::class, get_debug_type($provider)));
            }

            $schema = array_replace_recursive($schema ?? [], $provider->getSchemaFragment());
        }
    }
}
