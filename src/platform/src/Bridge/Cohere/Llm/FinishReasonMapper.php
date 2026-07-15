<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Llm;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the Cohere `finish_reason`.
 *
 * `ERROR` normalizes to OTHER, but in practice the converter raises an exception before it surfaces.
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
            'COMPLETE' => FinishReasonCase::STOP,
            'MAX_TOKENS' => FinishReasonCase::LENGTH,
            'TOOL_CALL' => FinishReasonCase::TOOL_CALL,
            'STOP_SEQUENCE' => FinishReasonCase::STOP_SEQUENCE,
            default => FinishReasonCase::OTHER,
        }, $rawFinishReason);
    }
}
