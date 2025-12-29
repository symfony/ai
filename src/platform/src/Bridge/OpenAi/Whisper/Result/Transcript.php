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
final class Transcript
{
    /**
     * @param Segment[] $segments
     */
    public function __construct(
        private readonly string $text,
        private readonly string $language,
        private readonly float $duration,
        private readonly array $segments,
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * @return Segment[]
     */
    public function getSegments(): array
    {
        return $this->segments;
    }
}
