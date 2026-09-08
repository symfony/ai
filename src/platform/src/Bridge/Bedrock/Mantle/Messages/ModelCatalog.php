<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Claude models served through the Anthropic Messages API on Bedrock Mantle.
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
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::THINKING,
            Capability::TOOL_CALLING,
        ];

        $models = [
            'anthropic.claude-fable-5',
            'anthropic.claude-haiku-4-5',
            'anthropic.claude-mythos-5',
            'anthropic.claude-mythos-preview',
            'anthropic.claude-opus-4-7',
            'anthropic.claude-opus-4-8',
            'anthropic.claude-opus-5',
            'anthropic.claude-sonnet-5',
        ];

        $defaultModels = [];
        foreach ($models as $model) {
            $defaultModels[$model] = ['class' => Claude::class, 'capabilities' => $capabilities];
        }

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
