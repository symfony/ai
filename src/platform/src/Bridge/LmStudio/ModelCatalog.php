<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LmStudio;

use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends FallbackModelCatalog
{
    // LmStudio can use any model that is loaded locally
    // Models are dynamically available based on what's loaded in LmStudio
}
