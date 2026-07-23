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
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        // STATIC LIST START
        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.
        $defaultModels = [
            'claude-3-5-haiku-20241022' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-3-5-haiku-latest' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-3-5-sonnet-20240620' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-3-5-sonnet-20241022' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-3-7-sonnet-20250219' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-3-haiku-20240307' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-3-opus-20240229' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-3-sonnet-20240229' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-fable-5' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-haiku-4-5' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
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
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-0' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
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
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-1-20250805' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-20250514' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-5' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-opus-4-5-20251101' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-6' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-7' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-opus-4-8' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-0' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
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
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-5' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'claude-sonnet-4-5-20250929' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-4-6' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'claude-sonnet-5' => [
                'class' => Claude::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
        ];
        // STATIC LIST END

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
