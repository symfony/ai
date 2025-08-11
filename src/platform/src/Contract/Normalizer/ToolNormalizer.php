<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @phpstan-import-type JsonSchema from Factory
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ToolNormalizer extends ModelContractNormalizer
{
    protected function supportedDataClass(): string
    {
        return Tool::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model->supports(Capability::TOOL_CALLING);
    }

    /**
     * @param Tool $data
     *
     * @return array{
     *     type: 'function',
     *     function: array{
     *         name: string,
     *         description: string,
     *         parameters?: JsonSchema
     *     }
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $function = [
            'name' => $data->name,
            'description' => $data->description,
        ];

        if (isset($data->parameters)) {
            $function['parameters'] = $data->parameters;
        }

        return [
            'type' => 'function',
            'function' => $function,
        ];
    }
}
