<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

/**
 * Represents a narration block an assistant emitted next to its answer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Commentary implements ContentInterface
{
    public function __construct(
        private readonly string $content,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
