<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AiMlApi\Completions;

use Symfony\AI\Platform\Bridge\AiMlApi\Completions;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Default\Completions\ModelClient as BaseModelClient;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de
 */
final class ModelClient extends BaseModelClient
{
    public function supports(Model $model): bool
    {
        return $model instanceof Completions;
    }
}
