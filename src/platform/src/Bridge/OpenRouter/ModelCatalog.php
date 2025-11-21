<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(
        array $additionalModels = [],
    ) {
        // OpenRouter provides access to many different models from various providers
        // This catalog only contains embeddings as default models
        // All regular models are added dynamically in the format provider/model als Models.
        // For a full and up-2-date list of models incl. capabilities, use the ModelApiCatalog
        $defaultModels = [
            'thenlper/gte-base' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'thenlper/gte-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/e5-large-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/e5-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/multilingual-e5-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/paraphrase-minilm-l6-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-minilm-l12-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-base-en-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/multi-qa-mpnet-base-dot-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-large-en-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-m3' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-mpnet-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-minilm-l6-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/mistral-embed-2312' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'google/gemini-embedding-001' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-ada-002' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/codestral-embed-2505' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-3-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-3-small' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'qwen/qwen3-embedding-8b' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'qwen/qwen3-embedding-4b' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }

    public function getModel(string $modelName): Model
    {
        if ('' === $modelName) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $parsed = $this->parseModelName($modelName);
        $actualModelName = $parsed['name'];
        $catalogKey = $parsed['catalogKey'];
        $options = $parsed['options'];

        if (!isset($this->models[$catalogKey])) {
            // Add model to the list as default model
            $this->models[$catalogKey] = [
                'class' => Model::class,
                'capabilities' => [],
            ];
        }

        $modelConfig = $this->models[$catalogKey];
        $modelClass = $modelConfig['class'];

        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        $model = new $modelClass($actualModelName, $modelConfig['capabilities'], $options);
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend "%s".', $modelClass, Model::class));
        }

        return $model;
    }
}
