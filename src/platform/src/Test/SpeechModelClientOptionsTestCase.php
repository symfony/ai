<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test;

use Symfony\AI\Platform\PlatformOptions;

/**
 * Base test case checking that a speech-to-text or text-to-speech model client sends the canonical platform options as its provider expects them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class SpeechModelClientOptionsTestCase extends ModelClientOptionsTestCase
{
    public static function canonicalValues(): array
    {
        return [
            PlatformOptions::LANGUAGE => 'de',
        ];
    }
}
