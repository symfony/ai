<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LiteLlm;

use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelCatalog extends FallbackModelCatalog
{
    // LiteLLM can use any model that is loaded locally
    // Models are dynamically available based on what's configured in LiteLLM
}
