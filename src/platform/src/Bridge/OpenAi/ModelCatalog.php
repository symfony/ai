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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @phpstan-type OpenAiModelName 'gpt-3.5-turbo'|'gpt-3.5-turbo-instruct'|'gpt-4'|'gpt-4-turbo'|'gpt-4o'|'gpt-4o-mini'|'gpt-4o-audio-preview'|'o1-mini'|'o1-preview'|'o3-mini'|'o3-mini-high'|'gpt-4.5-preview'|'gpt-4.1'|'gpt-4.1-mini'|'gpt-4.1-nano'|'gpt-5'|'gpt-5-chat-latest'|'gpt-5-mini'|'gpt-5-nano'|'text-embedding-ada-002'|'text-embedding-3-large'|'text-embedding-3-small'|'whisper-1'|'dall-e-2'|'dall-e-3'
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = require __DIR__.'/Resources/models.php';

        $this->models = array_merge($defaultModels, $additionalModels);
    }

    /**
     * Get an OpenAI model by name.
     *
     * For better IDE autocompletion, use the Model class constants:
     *
     *     $model = $catalog->getModel(Model::GPT_4O);
     *
     * @param OpenAiModelName $modelName The model identifier (e.g., 'gpt-4o', 'gpt-4o-mini')
     */
    public function getModel(string $modelName): \Symfony\AI\Platform\Model
    {
        return parent::getModel($modelName);
    }
}
