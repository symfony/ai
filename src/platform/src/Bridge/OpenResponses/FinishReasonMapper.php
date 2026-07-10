<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the Responses API completion state.
 *
 * Unlike chat completions there is no per-choice `finish_reason`: a successful response reports
 * `status: completed`, an aborted one `status: incomplete` plus an `incomplete_details.reason`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FinishReasonMapper
{
    /**
     * The Responses API reports `completed` even when the model stopped to call a function, so a
     * tool call in the output upgrades an otherwise clean stop to TOOL_CALL. It never overrides a
     * `LENGTH` or `CONTENT_FILTER` reason: a truncated response stays truncated even if the partial
     * output happens to contain a tool call.
     *
     * The raw provider value is preserved as reported either way.
     */
    public static function map(?string $rawFinishReason, bool $stoppedForToolCall = false): ?FinishReason
    {
        if (null === $rawFinishReason || '' === $rawFinishReason) {
            return null;
        }

        $case = match ($rawFinishReason) {
            'completed' => FinishReasonCase::STOP,
            'max_output_tokens' => FinishReasonCase::LENGTH,
            'content_filter' => FinishReasonCase::CONTENT_FILTER,
            default => FinishReasonCase::OTHER,
        };

        if ($stoppedForToolCall && FinishReasonCase::STOP === $case) {
            $case = FinishReasonCase::TOOL_CALL;
        }

        return new FinishReason($case, $rawFinishReason);
    }
}
