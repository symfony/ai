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

    public function getRemainingTokens(): ?int;

    public function getRemainingTokensMinute(): ?int;

    public function getRemainingTokensMonth(): ?int;

    public function getTotalTokens(): ?int;
}
