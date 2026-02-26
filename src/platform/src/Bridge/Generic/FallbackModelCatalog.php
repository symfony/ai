<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * A fallback model catalog for the Generic bridge that creates the
 * appropriate model subclass based on the model name.
 *
 * The Generic bridge requires models to be instances of CompletionsModel
 * or EmbeddingsModel for the corresponding ModelClient to accept them.
 * This catalog uses a naming convention (model name contains "embed")
 * to determine the correct type when no explicit catalog is provided.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FallbackModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $parsed = self::parseModelName($modelName);

        if (str_contains(strtolower($parsed['name']), 'embed')) {
            return new EmbeddingsModel($parsed['name'], Capability::cases(), $parsed['options']);
        }

        return new CompletionsModel($parsed['name'], Capability::cases(), $parsed['options']);
    }
}
