<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            // Chat models
            'command-a-03-2025' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'command-a-vision-07-2025' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'command-a-translate-08-2025' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'command-a-reasoning-08-2025' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'command-r-plus-08-2024' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'command-r-08-2024' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'command-r7b-12-2024' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Aya models
            'c4ai-aya-expanse-32b' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'c4ai-aya-vision-32b' => [
                'class' => Cohere::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            // Embedding models
            'embed-v4.0' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::INPUT_MULTIMODAL, Capability::EMBEDDINGS],
            ],
            'embed-english-v3.0' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'embed-multilingual-v3.0' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'embed-english-light-v3.0' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'embed-multilingual-light-v3.0' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            // Reranking models
            'rerank-v4.0-pro' => [
                'class' => Reranker::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::RERANKING],
            ],
            'rerank-v4.0-fast' => [
                'class' => Reranker::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::RERANKING],
            ],
            'rerank-v3.5' => [
                'class' => Reranker::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::RERANKING],
            ],
            'rerank-english-v3.0' => [
                'class' => Reranker::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::RERANKING],
            ],
            'rerank-multilingual-v3.0' => [
                'class' => Reranker::class,
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::RERANKING],
            ],
            // Speech-to-text models
            'cohere-transcribe-03-2026' => [
                'class' => SpeechToText::class,
                'capabilities' => [Capability::INPUT_AUDIO, Capability::SPEECH_TO_TEXT],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }

    protected function endpointsForModel(array $modelConfig): array
    {
        $class = $modelConfig['class'];

        return match ($class) {
            Cohere::class => [new Endpoint(ChatClient::ENDPOINT)],
            Embeddings::class => [new Endpoint(EmbedClient::ENDPOINT)],
            Reranker::class => [new Endpoint(RerankClient::ENDPOINT)],
            SpeechToText::class => [new Endpoint(TranscriptionClient::ENDPOINT)],
            default => [],
        };
    }
}
