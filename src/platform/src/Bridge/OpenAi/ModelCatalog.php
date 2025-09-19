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
        $defaultModels = $this->getDefaultOpenAiModels();

        $this->models = array_merge($defaultModels, $additionalModels);
    }

    /**
     * @return array<string, array{class: string, platform: string, capabilities: list<Capability>}>
     */
    private function getDefaultOpenAiModels(): array
    {
        return [
            // GPT Models - All GPT models from Gpt.php
            'gpt-3.5-turbo' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-3.5-turbo'),
            ],
            'gpt-3.5-turbo-instruct' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-3.5-turbo-instruct'),
            ],
            'gpt-4' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4'),
            ],
            'gpt-4-turbo' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4-turbo'),
            ],
            'gpt-4o' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4o'),
            ],
            'gpt-4o-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4o-mini'),
            ],
            'gpt-4o-audio-preview' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4o-audio-preview'),
            ],
            'o1-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('o1-mini'),
            ],
            'o1-preview' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('o1-preview'),
            ],
            'o3-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('o3-mini'),
            ],
            'o3-mini-high' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('o3-mini-high'),
            ],
            'gpt-4.5-preview' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4.5-preview'),
            ],
            'gpt-4.1' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4.1'),
            ],
            'gpt-4.1-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4.1-mini'),
            ],
            'gpt-4.1-nano' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-4.1-nano'),
            ],
            'gpt-5' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-5'),
            ],
            'gpt-5-chat-latest' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-5-chat-latest'),
            ],
            'gpt-5-mini' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-5-mini'),
            ],
            'gpt-5-nano' => [
                'class' => Gpt::class,
                'platform' => 'openai',
                'capabilities' => $this->getGptCapabilities('gpt-5-nano'),
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
    }

    /**
     * @return list<Capability>
     */
    private function getGptCapabilities(string $modelName): array
    {
        // Base capabilities for all GPT models
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ];

        // Tool calling capability (all models except gpt-5-chat-latest)
        if ('gpt-5-chat-latest' !== $modelName) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        // Audio capability (only gpt-4o-audio-preview)
        if ('gpt-4o-audio-preview' === $modelName) {
            $capabilities[] = Capability::INPUT_AUDIO;
        }

        // Image supporting models
        $imageSupportingModels = [
            'gpt-4-turbo',
            'gpt-4o',
            'gpt-4o-mini',
            'o1-mini',
            'o1-preview',
            'o3-mini',
            'gpt-4.5-preview',
            'gpt-4.1',
            'gpt-4.1-mini',
            'gpt-4.1-nano',
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
            'gpt-5-chat-latest',
        ];
        if (in_array($modelName, $imageSupportingModels, true)) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        // Structured output supporting models
        $structuredOutputSupportingModels = [
            'gpt-4o',
            'gpt-4o-mini',
            'o3-mini',
            'gpt-4.5-preview',
            'gpt-4.1',
            'gpt-4.1-mini',
            'gpt-4.1-nano',
            'gpt-5',
            'gpt-5-mini',
            'gpt-5-nano',
        ];
        if (in_array($modelName, $structuredOutputSupportingModels, true)) {
            $capabilities[] = Capability::OUTPUT_STRUCTURED;
        }

        return $capabilities;
    }
}
