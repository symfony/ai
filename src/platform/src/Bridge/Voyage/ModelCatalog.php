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
     * @param array<string, array{class: string, label: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'voyage-3.5' => [
                'class' => Voyage::class,
                'label' => 'Voyage 3.5 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-3.5-lite' => [
                'class' => Voyage::class,
                'label' => 'Voyage 3.5 Lite (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-3' => [
                'class' => Voyage::class,
                'label' => 'Voyage 3 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-3-lite' => [
                'class' => Voyage::class,
                'label' => 'Voyage 3 Lite (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-3-large' => [
                'class' => Voyage::class,
                'label' => 'Voyage 3 Large (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-finance-2' => [
                'class' => Voyage::class,
                'label' => 'Voyage Finance 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-multilingual-2' => [
                'class' => Voyage::class,
                'label' => 'Voyage Multilingual 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-law-2' => [
                'class' => Voyage::class,
                'label' => 'Voyage Law 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-code-3' => [
                'class' => Voyage::class,
                'label' => 'Voyage Code 3 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-code-2' => [
                'class' => Voyage::class,
                'label' => 'Voyage Code 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-multimodal-3' => [
                'class' => Voyage::class,
                'label' => 'Voyage Multimodal 3 (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_MULTIPLE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
