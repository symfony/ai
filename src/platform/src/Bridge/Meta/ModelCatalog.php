<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Meta;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'llama-3.3-70B-Instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-90b-vision-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'llama-3.2-11b-vision-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'llama-3.2-3b' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-3b-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-1b' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-1b-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-405b-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-70b' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-70b-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-8b' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-8b-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-70b' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-8b-instruct' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3-8b' => [
                'class' => Llama::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
