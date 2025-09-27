<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Model;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabs extends Model
{
    // text-to-speech models
    public const ELEVEN_V3 = 'eleven_v3';
    public const ELEVEN_TTV_V3 = 'eleven_ttv_v3';
    public const ELEVEN_MULTILINGUAL_V2 = 'eleven_multilingual_v2';
    public const ELEVEN_FLASH_V250 = 'eleven_flash_v2_5';
    public const ELEVEN_FLASH_V2 = 'eleven_flashv2';
    public const ELEVEN_TURBO_V2_5 = 'eleven_turbo_v2_5';
    public const ELEVEN_TURBO_V2 = 'eleven_turbo_v2';
    public const ELEVEN_MULTILINGUAL_STS_V2 = 'eleven_multilingual_sts_v2';
    public const ELEVEN_MULTILINGUAL_TTV_V2 = 'eleven_multilingual_ttv_v2';
    public const ELEVEN_ENGLISH_STS_V2 = 'eleven_english_sts_v2';

    // speech-to-text models
    public const SCRIBE_V1 = 'scribe_v1';
    public const SCRIBE_V1_EXPERIMENTAL = 'scribe_v1_experimental';
}
