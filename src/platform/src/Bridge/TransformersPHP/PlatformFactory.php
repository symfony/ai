<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TransformersPHP;

use Codewithkyrian\Transformers\Transformers;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class PlatformFactory
{
    public static function create(): Platform
    {
        if (!class_exists(Transformers::class)) {
            throw new RuntimeException('TransformersPHP is not installed. Please install it using "composer require codewithkyrian/transformers".');
        }

        return new Platform();
    }
}
