<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPhp;

use Codewithkyrian\Transformers\Transformers;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Platform;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class PlatformFactory
{
    public static function create(ModelCatalogInterface $modelCatalog = new ModelCatalog()): Platform
    {
        if (!class_exists(Transformers::class)) {
            throw new RuntimeException('For using the TransformersPHP with FFI to run models in PHP, the codewithkyrian/transformers package is required. Try running "composer require codewithkyrian/transformers".');
        }

        return new Platform([new ModelClient()], [new ResultConverter()], $modelCatalog);
    }
}
