<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'voyage-3.5' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-3.5-lite' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-3' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-3-lite' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-3-large' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-finance-2' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-multilingual-2' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-law-2' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-code-3' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-code-2' => [
                'class' => Voyage::class,
                'capabilities' => [Capability::INPUT_MULTIPLE],
            ],
            'voyage-multimodal-3' => [
                'class' => Voyage::class,
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::INPUT_MULTIMODAL,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
