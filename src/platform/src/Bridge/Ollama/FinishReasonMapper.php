<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;

/**
 * Maps the Ollama `done_reason`.
 *
 * `load` and `unload` report a model lifecycle event rather than a generation outcome, so they
 * normalize to OTHER.
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
            default => FinishReasonCase::OTHER,
        }, $rawFinishReason);
    }
}
