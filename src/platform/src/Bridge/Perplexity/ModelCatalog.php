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
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $presetCapabilities = [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
        ];

        $defaultModels = [
            'fast' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'low' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'medium' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'high' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'xhigh' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'perplexity/sonar' => [
                'class' => Perplexity::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Deprecated Sonar Chat Completions models, sent as their replacement preset
            'sonar' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'sonar-pro' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'sonar-reasoning' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'sonar-reasoning-pro' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
            'sonar-deep-research' => ['class' => Perplexity::class, 'capabilities' => $presetCapabilities],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
