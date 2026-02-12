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
 * Represents model reasoning/thinking content.
 *
 * Some LLM models expose their "chain of thought" or reasoning process
 * separately from the final response. This content type captures that
 * intermediate reasoning.
 *
 * Examples of models that support thinking:
 * - DeepSeek R1
 * - Claude with extended thinking mode
 * - OpenAI o1/o3 (reasoning models)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Thinking implements ContentInterface
{
    public function __construct(
        private readonly string $thinking,
    ) {
    }

    public function getThinking(): string
    {
        return $this->thinking;
    }
}
