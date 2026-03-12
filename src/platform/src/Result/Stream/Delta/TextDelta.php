<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream\Delta;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TextDelta implements DeltaInterface, \Stringable
{
    public function __construct(
        private readonly string $text,
    ) {
    }

    public function __toString(): string
    {
        return $this->text;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
