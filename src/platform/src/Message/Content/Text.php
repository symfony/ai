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
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class Text implements ContentInterface
{
    public function __construct(
        private readonly string $text,
        private readonly ?string $signature = null,
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Provider-scoped signature guarding this text part when replayed on a subsequent turn.
     * Currently only Google Gemini / Vertex AI emit signatures on non-thought text parts.
     */
    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
