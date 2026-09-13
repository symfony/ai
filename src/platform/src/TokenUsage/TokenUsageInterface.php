<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\TokenUsage;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface TokenUsageInterface
{
    public function getPromptTokens(): ?int;

    public function getCompletionTokens(): ?int;

    public function getThinkingTokens(): ?int;

    public function getToolTokens(): ?int;

    public function getCachedTokens(): ?int;

    /**
     * Tokens written into the prompt cache on this request (mainly for Anthropic, null when not reported by the model).
     */
    public function getCacheCreationTokens(): ?int;

    /**
     * Tokens served from the prompt cache on this request (mainly for Anthropic, null when not reported by the model).
     */
    public function getCacheReadTokens(): ?int;

    public function getRemainingTokens(): ?int;

    public function getRemainingTokensMinute(): ?int;

    public function getRemainingTokensMonth(): ?int;

    public function getTotalTokens(): ?int;

    /**
     * The model that consumed these tokens, as the provider reports it in its response - which is
     * usually the resolved snapshot ("gpt-4o-2024-08-06") rather than the alias that was requested.
     *
     * Null when the provider does not name a model in the payload the usage was read from.
     */
    public function getModel(): ?string;
}
