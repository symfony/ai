<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Whisper
{
    public const WHISPER_1 = 'whisper-1';

    /**
     * @param array<string, mixed> $options
     */
    public static function create(string $name = self::WHISPER_1, array $options = []): Model
    {
        $capabilities = [
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
        ];

        return new Model($name, $capabilities, $options);
    }
}
