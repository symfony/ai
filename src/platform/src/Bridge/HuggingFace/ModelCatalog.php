<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\ModelCatalog\DynamicModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends DynamicModelCatalog
{
    // HuggingFace supports a wide range of models dynamically
    // Models are identified by repository/model format (e.g., "microsoft/DialoGPT-medium")
}
