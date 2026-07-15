<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the Amazon Bedrock `stopReason` (Converse API, used by Nova) and the `stop_reason` of the
 * Bedrock-hosted Meta Llama models.
 *
 * The Bedrock Claude bridge uses the Anthropic mapper instead, since it returns the Anthropic schema.
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
            'end_turn', 'stop' => FinishReasonCase::STOP,
            'max_tokens', 'length' => FinishReasonCase::LENGTH,
            'tool_use' => FinishReasonCase::TOOL_CALL,
            'stop_sequence' => FinishReasonCase::STOP_SEQUENCE,
            'guardrail_intervened', 'content_filtered' => FinishReasonCase::CONTENT_FILTER,
            default => FinishReasonCase::OTHER,
        }, $rawFinishReason);
    }
}
