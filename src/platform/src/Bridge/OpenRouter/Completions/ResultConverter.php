<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Completions;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ResultConverter\CompletionsResultConverter;

/**
 * @author rglozman
 */
final class ResultConverter extends CompletionsResultConverter
{
    public function supports(Model $model): bool
    {
        return true;
    }
}
