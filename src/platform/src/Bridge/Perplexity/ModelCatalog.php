<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, label: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'sonar' => [
                'class' => Perplexity::class,
                'label' => 'Sonar',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'sonar-pro' => [
                'class' => Perplexity::class,
                'label' => 'Sonar Pro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'sonar-reasoning' => [
                'class' => Perplexity::class,
                'label' => 'Sonar Reasoning',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'sonar-reasoning-pro' => [
                'class' => Perplexity::class,
                'label' => 'Sonar Reasoning Pro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'sonar-deep-research' => [
                'class' => Perplexity::class,
                'label' => 'Sonar Deep Research',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    // Note: sonar-deep-research does not support INPUT_IMAGE
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
