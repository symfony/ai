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

use Symfony\AI\Platform\Model;

/**
 * Marker model class for Bifrost speech-to-text requests, routed through
 * `/v1/audio/transcriptions` or `/v1/audio/translations`. Bifrost typically
 * expects model names such as `openai/whisper-1` or `openai/gpt-4o-transcribe`.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
class TranscriptionModel extends Model
{
}
