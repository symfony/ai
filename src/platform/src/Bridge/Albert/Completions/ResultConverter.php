<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Completions;

use Symfony\AI\Platform\Bridge\Albert\Completions;
use Symfony\AI\Platform\Default\Completions\ResultConverter as BaseResultConverter;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter extends BaseResultConverter
{
    public function supports(Model $model): bool
    {
        return $model instanceof Completions;
    }
}
