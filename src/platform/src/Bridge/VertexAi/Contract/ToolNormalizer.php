<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Contract;

use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 *
 * @phpstan-import-type JsonSchema from Factory
 */
final class ToolNormalizer extends ModelContractNormalizer
{
    /**
     * @param Tool $data
     *
     * @return array{
     *     name: string,
     *     description: string,
     *     parameters: JsonSchema|array{type: 'object'}
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $parameters = $data->getParameters() ? $this->normalizeSchema($data->getParameters()) : null;

        return [
            'name' => $data->getName(),
            'description' => $data->getDescription(),
            'parameters' => $parameters,
        ];
    }

    protected function supportedDataClass(): string
    {
        return Tool::class;
    }

    protected function supportsModel(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    /**
     * Normalizes a JSON Schema for VertexAI compatibility.
     *
     * - Removes 'additionalProperties' (not supported by VertexAI)
     * - Converts array-style nullable types ['string', 'null'] to ['type' => 'string', 'nullable' => true]
     *
     * @template T of array
     *
     * @phpstan-param T $data
     *
     * @phpstan-return T
     */
    private function normalizeSchema(array $data): array
    {
        unset($data['additionalProperties']);

        // Convert array-style nullable types to VertexAI format
        if (isset($data['type']) && \is_array($data['type'])) {
            $nullIndex = array_search('null', $data['type'], true);
            if (false !== $nullIndex) {
                $types = $data['type'];
                unset($types[$nullIndex]);
                $types = array_values($types);

                if (1 === \count($types)) {
                    $data['type'] = $types[0];
                    $data['nullable'] = true;
                }
            }
        }

        foreach ($data as &$value) {
            if (\is_array($value)) {
                $value = $this->normalizeSchema($value);
            }
        }

        return $data;
    }
}
