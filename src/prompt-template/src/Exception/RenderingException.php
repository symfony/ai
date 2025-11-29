<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate\Exception;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RenderingException extends RuntimeException
{
    public static function expressionEvaluationFailed(string $expression, \Throwable $previous): self
    {
        return new self(\sprintf('Failed to render expression "%s": %s', $expression, $previous->getMessage()), previous: $previous);
    }

    public static function templateProcessingFailed(): self
    {
        return new self('Failed to process template.');
    }
}
