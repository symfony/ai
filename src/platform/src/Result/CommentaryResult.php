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
 * Narration of what the model is about to do, reported next to the answer instead of as part of it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CommentaryResult extends BaseResult
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
