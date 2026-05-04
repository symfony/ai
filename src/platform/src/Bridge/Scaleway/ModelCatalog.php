<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Symfony\AI\Platform\Bridge\Generic\ChatCompletionsClient;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Endpoint;
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
        $defaultModels = [
            'deepseek-r1-distill-llama-70b' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gemma-3-27b-it' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'llama-3.1-8b-instruct' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'llama-3.3-70b-instruct' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'devstral-small-2505' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistral-nemo-instruct-2407' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'pixtral-12b-2409' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistral-small-3.2-24b-instruct-2506' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-oss-120b' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen3-coder-30b-a3b-instruct' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen3-235b-a22b-instruct-2507' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen3.5-397b-a17b' => [
                'class' => Scaleway::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                ],
            ],
            'qwen3-embedding-8b' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ],
            'bge-multilingual-gemma2' => [
                'class' => Embeddings::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }

    protected function endpointsForModel(array $modelConfig): array
    {
        $class = $modelConfig['class'];

        return match ($class) {
            Scaleway::class => [new Endpoint(ChatCompletionsClient::ENDPOINT)],
            Embeddings::class => [new Endpoint(EmbeddingsClient::ENDPOINT)],
            default => [],
        };
    }
}
