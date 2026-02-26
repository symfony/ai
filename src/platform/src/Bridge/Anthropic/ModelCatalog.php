<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

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
            'claude-3-haiku-20240307' => [
                'class' => Claude::class,
                'label' => 'Claude 3 Haiku',
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
                'label' => 'Claude 3 Opus',
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
                'label' => 'Claude 3.5 Haiku',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-5-haiku-20241022' => [
                'class' => Claude::class,
                'label' => 'Claude 3.5 Haiku',
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
                'label' => 'Claude 3.7 Sonnet',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-3-7-sonnet-20250219' => [
                'class' => Claude::class,
                'label' => 'Claude 3.7 Sonnet',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-20250514' => [
                'class' => Claude::class,
                'label' => 'Claude Sonnet 4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
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
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-20250514' => [
                'class' => Claude::class,
                'label' => 'Claude Opus 4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
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
                    Capability::THINKING,
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
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-1-20250805' => ['class' => Claude::class,
                'label' => 'Claude Opus 4.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-5-20250929' => [
                'class' => Claude::class,
                'label' => 'Claude Sonnet 4.5',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-haiku-4-5-20251001' => [
                'class' => Claude::class,
                'label' => 'Claude Haiku 4.5',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
