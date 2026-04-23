<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * Cooperative interruption signal observable by long-running operations.
 *
 * Implementations are typically passed via options to a multi-phase pipeline
 * (e.g. SpeechAgent: STT → LLM → TTS) which checks `isInterrupted()` between
 * phases and aborts when the flag is set.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface InterruptionSignalInterface
{
    public function isInterrupted(): bool;
}
