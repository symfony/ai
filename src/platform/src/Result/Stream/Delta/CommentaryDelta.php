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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CommentaryDelta implements DeltaInterface, \Stringable
{
    public function __construct(
        private readonly string $commentary,
    ) {
    }

    public function __toString(): string
    {
        return $this->commentary;
    }

    public function getCommentary(): string
    {
        return $this->commentary;
    }
}
