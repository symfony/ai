<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\TextToSpeech;

/**
 * @author Anthony Hacart <anthony.hacart@gmail.com>
 */
interface Format
{
    public const MP3 = 'mp3';
    public const WAV = 'wav';
    public const PCM = 'pcm';
    public const FLAC = 'flac';
    public const OPUS = 'opus';
}
