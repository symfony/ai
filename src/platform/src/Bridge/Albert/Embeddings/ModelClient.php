<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Embeddings;

use Symfony\AI\Platform\Bridge\Albert\Embeddings;
use Symfony\AI\Platform\Default\Embeddings\ModelClient as BaseModelClient;
use Symfony\AI\Platform\Model;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelClient extends BaseModelClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }
}
