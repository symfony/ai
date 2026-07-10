<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the MiniMax `choices[].finish_reason`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FinishReasonMapper
{
    public static function map(?string $rawFinishReason): ?FinishReason
    {
        if (null === $rawFinishReason || '' === $rawFinishReason) {
            return null;
        }

        return new FinishReason(match ($rawFinishReason) {
            'stop' => FinishReasonCase::STOP,
            'length', 'max_tokens' => FinishReasonCase::LENGTH,
            'tool_calls' => FinishReasonCase::TOOL_CALL,
            'content_filter' => FinishReasonCase::CONTENT_FILTER,
            default => FinishReasonCase::OTHER,
        }, $rawFinishReason);
    }
}
