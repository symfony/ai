<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>, label: string}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'nova-micro' => [
                'class' => Nova::class,
                'label' => 'Amazon Nova Micro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nova-lite' => [
                'class' => Nova::class,
                'label' => 'Amazon Nova Lite',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'nova-pro' => [
                'class' => Nova::class,
                'label' => 'Amazon Nova Pro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'nova-premier' => [
                'class' => Nova::class,
                'label' => 'Amazon Nova Premier',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-3-7-sonnet-20250219' => [
                'class' => Claude::class,
                'label' => 'Claude 3.7 Sonnet 2025-02-19',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-haiku-20240307' => [
                'class' => Claude::class,
                'label' => 'Claude 3 Haiku 2024-03-07',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-5-haiku-latest' => [
                'class' => Claude::class,
                'label' => 'Claude 3.5 Haiku (Latest)',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-sonnet-20240229' => [
                'class' => Claude::class,
                'label' => 'Claude 3 Sonnet 2024-02-29',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-5-sonnet-latest' => [
                'class' => Claude::class,
                'label' => 'Claude 3.5 Sonnet (Latest)',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-7-sonnet-latest' => [
                'class' => Claude::class,
                'label' => 'Claude 3.7 Sonnet (Latest)',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-20250514' => [
                'class' => Claude::class,
                'label' => 'Claude Sonnet 4 2025-05-14',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-0' => [
                'class' => Claude::class,
                'label' => 'Claude Sonnet 4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-opus-20240229' => [
                'class' => Claude::class,
                'label' => 'Claude 3 Opus 2024-02-29',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-20250514' => [
                'class' => Claude::class,
                'label' => 'Claude Opus 4 2025-05-14',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-0' => [
                'class' => Claude::class,
                'label' => 'Claude Opus 4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-1' => [
                'class' => Claude::class,
                'label' => 'Claude Opus 4.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-5-20251101' => [
                'class' => Claude::class,
                'label' => 'Claude Opus 4.5 2025-11-01',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-5-20250929' => [
                'class' => Claude::class,
                'label' => 'Claude Sonnet 4.5 2025-09-29',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'llama-3.3-70B-Instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.3 70B (Instruct)',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-90b-vision-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 90B Vision (Instruct)',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.2-11b-vision-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.2 11B Vision (Instruct)',
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
                'label' => 'Llama 3.2 3B (Instruct)',
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
                'label' => 'Llama 3.2 1B (Instruct)',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'llama-3.1-405b-instruct' => [
                'class' => Llama::class,
                'label' => 'Llama 3.1 405B (Instruct)',
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
                'label' => 'Llama 3 70B (Instruct)',
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
                'label' => 'Llama 3.1 8B (Instruct)',
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
                'label' => 'Llama 3 8B (Instruct)',
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
