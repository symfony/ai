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

use Symfony\AI\Platform\Model;

/**
 * @author André Lubian <lubiana123@gmail.com>
 */
final class Embeddings
{
    public static function create(string $name, array $capabilities = [], array $options = []): Model
    {
        return new Model($name, $capabilities, $options);
    }
}
