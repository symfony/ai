<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsLab;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Adhik Joshi <adhik@modelslab.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            // Flux models
            'flux' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
            'flux-pro' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
            // Stable Diffusion XL
            'sdxl' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
            'juggernaut-xl' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
            'realvisxl-v4.0' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
            // Stable Diffusion 1.5 / 2.x
            'stable-diffusion' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
            'dreamshaper' => [
                'class' => ModelsLab::class,
                'capabilities' => [Capability::TEXT_TO_IMAGE],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
