<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface Format
{
    public const WAV = 'wav';
    public const PCM = 'pcm';
}
