<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Gemini;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the Gemini `candidates[].finishReason`.
 *
 * Reused by the Vertex AI Gemini bridge, which speaks the same payload schema. Gemini reports a plain
 * `STOP` when the model stopped to call a function, so there is no TOOL_CALL mapping here.
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
            'STOP' => FinishReasonCase::STOP,
            'MAX_TOKENS' => FinishReasonCase::LENGTH,
            'SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'IMAGE_SAFETY' => FinishReasonCase::CONTENT_FILTER,
            default => FinishReasonCase::OTHER,
        }, $rawFinishReason);
    }
}
