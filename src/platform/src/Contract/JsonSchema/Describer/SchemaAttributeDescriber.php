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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Exception\IOException;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class SchemaAttributeDescriber implements PropertyDescriberInterface
{
    /**
     * @var array<string, SchemaProviderInterface>
     */
    private readonly array $providers;

    /**
     * @param iterable<string, SchemaProviderInterface> $providers Indexed by service ID (FQCN by default)
     */
    public function __construct(iterable $providers = [])
    {
        $this->providers = $providers instanceof \Traversable ? iterator_to_array($providers) : $providers;
    }

    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        foreach ($subject->getAttributes(Schema::class) as $attribute) {
            if ($attribute->ref) {
                try {
                    $attributeSchema = json_decode(file_get_contents($attribute->ref), true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new IOException(\sprintf('Failed to load the schema from "%s"', $attribute->ref), 0, $e);
                }
            } else {
                $attributeSchema = array_filter((array) $attribute, static fn ($value) => null !== $value && [] !== $value);
                unset($attributeSchema['provider'], $attributeSchema['context']);
            }

            $schema = array_replace_recursive($schema ?? [], $attributeSchema);

            if (null !== $attribute->provider) {
                if (!isset($this->providers[$attribute->provider])) {
                    throw new RuntimeException(\sprintf('Schema provider "%s" is not registered. Register it as a service tagged "ai.platform.json_schema.provider" or pass it to the describer.', $attribute->provider));
                }

                $schema = array_replace_recursive($schema, $this->providers[$attribute->provider]->getSchemaFragment($attribute->context));
            }
        }
    }
}
