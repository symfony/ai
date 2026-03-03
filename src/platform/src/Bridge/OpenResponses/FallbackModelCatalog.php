<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * A fallback model catalog that accepts any model name and creates ResponsesModel instances with all capabilities.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
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

        return new ResponsesModel($parsed['name'], Capability::cases(), $parsed['options']);
    }
}
