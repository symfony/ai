<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Whisper\Result;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Segment
{
    public function __construct(
        private readonly float $start,
        private readonly float $end,
        private readonly string $text,
    ) {
    }

    public function getStart(): float
    {
        return $this->start;
    }

    public function getEnd(): float
    {
        return $this->end;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
