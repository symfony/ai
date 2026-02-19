<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, label: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'llama-3.3-70B-Instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.3 70B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-90b-vision-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 90B Vision Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-11b-vision-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 11B Vision Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-3b' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 3B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-3b-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 3B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-1b' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 1B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-1b-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 1B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-405b-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.1 405B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-70b' => [
                'class' => Llama::class,
                'label' => 'Llama 3.1 70B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-70b-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3 70B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-8b' => [
                'class' => Llama::class,
                'label' => 'Llama 3.1 8B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-8b-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.1 8B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-70b' => [
                'class' => Llama::class,
                'label' => 'Llama 3 70B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-8b-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3 8B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-8b' => [
                'class' => Llama::class,
                'label' => 'Llama 3 8B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
