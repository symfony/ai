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
 * Response audio formats accepted by Bifrost text-to-speech requests.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface Format
{
    public const MP3 = 'mp3';
    public const OPUS = 'opus';
    public const AAC = 'aac';
    public const FLAC = 'flac';
    public const WAV = 'wav';
    public const PCM = 'pcm';
}
