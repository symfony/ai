<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelCatalog;

/*
 * A dynamic model catalog that accepts any model name and creates models with all capabilities.
 *
 * This class is useful for platforms that support a wide range of models dynamically
 * without needing to predefine them in a static catalog. Since we don't know what specific
 * capabilities each dynamic model supports, we provide all capabilities by default.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

class DynamicModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $parsed = self::parseModelName($modelName);

        return new Model($parsed['name'], Capability::cases(), $parsed['options']);
    }
}
