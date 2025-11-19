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

use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends FallbackModelCatalog
{
    // TransformersPhp can use various models from HuggingFace
    // dynamically loaded through transformers.php library
}
