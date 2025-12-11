<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\OpenAi;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog as OpenAiModelCatalog;
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
        $defaultModels = (new OpenAiModelCatalog())->getModels();
        foreach ($defaultModels as $modelName => $modelData) {
            $defaultModels[$modelName] = $modelData;

            if (Gpt::class === $modelData['class']) {
                $defaultModels[$modelName]['class'] = CompletionsModel::class;
            }
            if (Embeddings::class === $modelData['class']) {
                $defaultModels[$modelName]['class'] = EmbeddingsModel::class;
            }
        }

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
