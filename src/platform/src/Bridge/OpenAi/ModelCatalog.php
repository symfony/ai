<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\AbstractModelCatalog;
use Symfony\AI\Platform\Capability;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, platform: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            // GPT Models - All GPT models from Gpt.php
            'gpt-3.5-turbo' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-3.5-turbo-instruct' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4-turbo' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-4o' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-audio-preview' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'o1-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o1-preview' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o3-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'o3-mini-high' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4.5-preview' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4.1' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4.1-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4.1-nano' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-5' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-5-chat-latest' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-5-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-5-nano' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            
            // Embedding Models - All embedding models from Embeddings.php
            'text-embedding-ada-002' => [
                'class' => Embeddings::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT],
            ],
            'text-embedding-3-large' => [
                'class' => Embeddings::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT],
            ],
            'text-embedding-3-small' => [
                'class' => Embeddings::class,
                'platform' => 'openai',
                'capabilities' => [Capability::INPUT_TEXT],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
