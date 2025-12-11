<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Add OpenRouter specific features to the model catalogues.
 *
 * "openrouter/auto" -> https://openrouter.ai/docs/features/model-routing#auto-router
 * "@preset/" -> https://openrouter.ai/docs/features/presets
 *
 *  Modifier are handled by the default parseModelName function
 *  ":nitro" -> https://openrouter.ai/docs/features/provider-routing#nitro-shortcut
 *  ":floor" -> https://openrouter.ai/docs/features/provider-routing#floor-price-shortcut
 *  ":exacto" -> https://openrouter.ai/docs/features/exacto-variant
 *  ":online" -> https://openrouter.ai/docs/features/web-search
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
abstract class AbstractOpenRouterModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $this->models = [
            'openrouter/auto' => [
                'class' => CompletionsModel::class,
                'capabilities' => Capability::cases(),
            ],
            '@preset' => [
                'class' => CompletionsModel::class,
                'capabilities' => Capability::cases(),
            ],
        ];
    }

    protected function parseModelName(string $modelName): array
    {
        if (str_starts_with($modelName, '@preset')) {
            return [
                'name' => $modelName,
                'catalogKey' => '@preset',
                'options' => [],
            ];
        }

        return parent::parseModelName($modelName);
    }
}
