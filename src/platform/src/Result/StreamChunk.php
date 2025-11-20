<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @author Oscar Esteve <oscarsdt@gmail.com>
 */
final class StreamChunk extends BaseResult implements \Stringable
{
    /**
     * @param string|iterable<mixed>|object|null $content
     */
    public function __construct(
        private readonly string|iterable|object|null $content,
    ) {
    }

    public function __toString(): string
    {
        return (string) $this->content;
    }

    public function getContent(): string|iterable|object|null
    {
        return $this->content;
    }
}
