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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextResult extends BaseResult
{
    public function __construct(
        private readonly string $content,
        private readonly ?string $signature = null,
        private readonly ?string $phase = null,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Provider-scoped signature guarding this text part when replayed on a subsequent turn.
     * Currently only Google Gemini / Vertex AI emit signatures on non-thought text parts.
     */
    public function getSignature(): ?string
    {
        return $this->signature;
    }

    /**
     * Phase the provider tagged this text with, e.g. "final_answer" of the OpenAI Responses API.
     */
    public function getPhase(): ?string
    {
        return $this->phase;
    }
}
