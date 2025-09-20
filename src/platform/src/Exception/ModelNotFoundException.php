<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class ModelNotFoundException extends \InvalidArgumentException implements ExceptionInterface
{
    public static function forModelName(string $modelName): self
    {
        return new self(\sprintf('Model "%s" not found in catalog.', $modelName));
    }
}
