<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Contract;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @author Valtteri R <valtzu@gmail.com>
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
        return [
            'description' => $data->getDescription(),
            'name' => $data->getName(),
            'parameters' => $data->getParameters() ? $this->removeAdditionalProperties($data->getParameters()) : null,
        ];
    }

    protected function supportedDataClass(): string
    {
        return Tool::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }

    /**
     * @template T of array
     *
     * @phpstan-param T $data
     *
     * @phpstan-return T
     */
    private function removeAdditionalProperties(array $data): array
    {
        unset($data['additionalProperties']); // not supported by Gemini

        foreach ($data as &$value) {
            if (\is_array($value)) {
                $value = $this->removeAdditionalProperties($value);
            }
        }

        return $data;
    }
}
