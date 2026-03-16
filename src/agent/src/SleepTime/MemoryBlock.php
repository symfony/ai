<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\SleepTime;

/**
 * A labeled, mutable memory block shared between the primary and sleeping agents.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MemoryBlock
{
    private string $content;

    public function __construct(
        private readonly string $label,
        string $content = '',
    ) {
        $this->content = $content;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
