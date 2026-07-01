<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\BedrockMantle;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Models served through the AWS Bedrock Mantle OpenAI-compatible endpoint.
 *
 * @author asrar <aszenz@gmail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ];

        // Open-weight models served through the Mantle Chat Completions route. The Gemma 4 family is
        // reached through the Responses route instead, see Responses\ModelCatalog.
        $models = [
            'openai.gpt-oss-120b',
            'openai.gpt-oss-20b',
            'qwen.qwen3-235b-a22b-2507',
            'qwen.qwen3-next-80b-a3b-instruct',
            'qwen.qwen3-32b',
            'qwen.qwen3-coder-30b-a3b-instruct',
        ];

        $defaultModels = [];
        foreach ($models as $model) {
            $defaultModels[$model] = ['class' => CompletionsModel::class, 'capabilities' => $capabilities];
        }

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
