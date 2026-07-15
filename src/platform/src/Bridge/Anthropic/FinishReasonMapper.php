<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the Anthropic Messages API `stop_reason`.
 *
 * Reused by the Amazon Bedrock Claude bridge, which speaks the same payload schema.
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
            'end_turn' => FinishReasonCase::STOP,
            'max_tokens' => FinishReasonCase::LENGTH,
            'tool_use' => FinishReasonCase::TOOL_CALL,
            'stop_sequence' => FinishReasonCase::STOP_SEQUENCE,
            'refusal' => FinishReasonCase::CONTENT_FILTER,
            default => FinishReasonCase::OTHER,
        }, $rawFinishReason);
    }
}
