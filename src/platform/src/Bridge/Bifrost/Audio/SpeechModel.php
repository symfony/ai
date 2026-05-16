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
 * Marker model class for Bifrost text-to-speech requests, routed through
 * `/v1/audio/speech`. Bifrost typically expects model names such as
 * `openai/tts-1` or `openai/gpt-4o-mini-tts`.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
class SpeechModel extends Model
{
}
