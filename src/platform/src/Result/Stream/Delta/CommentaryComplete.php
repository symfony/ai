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
 * Signals that a commentary block is complete with accumulated content.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CommentaryComplete implements DeltaInterface
{
    public function __construct(
        private readonly string $commentary,
    ) {
    }

    public function getCommentary(): string
    {
        return $this->commentary;
    }
}
