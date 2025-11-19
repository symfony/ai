<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
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
