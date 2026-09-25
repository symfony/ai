<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Selector\MissingPropertiesSelector;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResponseFormatFactory implements ResponseFormatFactoryInterface
{
    public function __construct(
        private readonly Factory $schemaFactory = new Factory(),
    ) {
    }

    public function create(string $responseClass, ?object $instanceToPopulate = null): array
    {
        $context = null !== $instanceToPopulate ? [Factory::CONTEXT_SELECTOR => new MissingPropertiesSelector($instanceToPopulate)] : [];
        $schema = $this->schemaFactory->buildProperties($responseClass, $context);

        if (null !== $instanceToPopulate && !isset($schema['properties'])) {
            throw new InvalidArgumentException(\sprintf('The given "%s" instance has no missing properties left to describe.', $responseClass));
        }

        if (null !== $schema) {
            $this->requireAllProperties($schema);
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => u($responseClass)->afterLast('\\')->toString(),
                'schema' => $schema,
                'strict' => true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function requireAllProperties(array &$schema): void
    {
        if (isset($schema['properties']) && \is_array($schema['properties'])) {
            $schema['required'] = array_keys($schema['properties']);
            $schema['additionalProperties'] = false;

            foreach ($schema['properties'] as &$property) {
                if (\is_array($property)) {
                    $this->requireAllProperties($property);
                }
            }
            unset($property);
        }

        foreach (['anyOf', 'oneOf', 'allOf'] as $composition) {
            if (!isset($schema[$composition]) || !\is_array($schema[$composition])) {
                continue;
            }

            foreach ($schema[$composition] as &$subSchema) {
                if (\is_array($subSchema)) {
                    $this->requireAllProperties($subSchema);
                }
            }
            unset($subSchema);
        }

        if (isset($schema['items']) && \is_array($schema['items'])) {
            $this->requireAllProperties($schema['items']);
        }
    }
}
