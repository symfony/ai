<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Contract;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @phpstan-import-type JsonSchema from Factory
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ToolNormalizer extends ModelContractNormalizer
{
    /**
     * @param Tool $data
     *
     * @return array{
     *     name: string,
     *     description: string,
     *     input_schema: JsonSchema|array{type: 'object'},
     *     defer_loading?: true
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $tool = [
            'name' => $data->getName(),
            'description' => $data->getDescription(),
            'input_schema' => $data->getParameters() ?? ['type' => 'object'],
        ];

        $model = $context[Contract::CONTEXT_MODEL] ?? null;
        if ($data->isDeferred() && $model instanceof Model && $model->supports(Capability::TOOL_SEARCH)) {
            $tool['defer_loading'] = true;
        }

        return $tool;
    }

    protected function supportedDataClass(): string
    {
        return Tool::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Claude;
    }
}
