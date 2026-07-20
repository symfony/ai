<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Audio;

/**
 * Tasks accepted by Bifrost speech-to-text requests. Transcription keeps the
 * source language whereas translation forces the response to English.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface Task
{
    public const TRANSCRIPTION = 'transcription';
    public const TRANSLATION = 'translation';
}
