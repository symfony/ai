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
 * Represents a thinking/reasoning block emitted by an assistant.
 *
 * The optional signature is used by providers such as Anthropic to verify
 * thinking blocks when they are replayed on a subsequent turn.
 */
final class Thinking implements ContentInterface
{
    public function __construct(
        private readonly string $content,
        private readonly ?string $signature = null,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
