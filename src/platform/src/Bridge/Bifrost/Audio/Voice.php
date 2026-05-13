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
 * Predefined voices accepted by Bifrost text-to-speech requests when the
 * underlying provider is OpenAI-compatible. The actual list of available
 * voices depends on the upstream provider routed by Bifrost.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface Voice
{
    public const ALLOY = 'alloy';
    public const ASH = 'ash';
    public const BALLAD = 'ballad';
    public const CORAL = 'coral';
    public const ECHO = 'echo';
    public const FABLE = 'fable';
    public const NOVA = 'nova';
    public const ONYX = 'onyx';
    public const SAGE = 'sage';
    public const SHIMMER = 'shimmer';
    public const VERSE = 'verse';
}
