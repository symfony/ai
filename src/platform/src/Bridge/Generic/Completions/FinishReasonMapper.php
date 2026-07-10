<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Completions;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the `choices[].finish_reason` of OpenAI-compatible chat completions APIs.
 *
 * Shared by every bridge speaking that schema, including the ones that only reuse this vocabulary
 * through their own converter (Azure Meta).
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
            'length' => FinishReasonCase::LENGTH,
            'tool_calls', 'function_call' => FinishReasonCase::TOOL_CALL,
            'content_filter' => FinishReasonCase::CONTENT_FILTER,
            default => FinishReasonCase::OTHER,
        }, $rawFinishReason);
    }
}
