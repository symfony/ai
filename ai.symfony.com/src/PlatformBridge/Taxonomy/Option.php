<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Taxonomy;

/**
 * A value a platform bridge can be filtered by. Implemented by string-backed enums.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
interface Option extends \BackedEnum
{
    public function label(): string;

    /**
     * A one-line explanation, rendered as a tooltip next to the option.
     */
    public function description(): string;
}
