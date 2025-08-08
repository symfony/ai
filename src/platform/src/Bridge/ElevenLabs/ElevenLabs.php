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
    public const TEXT_TO_SPEECH = 'text-to-speech';
    public const SPEECH_TO_TEXT = 'speech-to-text';

    public function __construct(
        string $name = self::TEXT_TO_SPEECH,
        array $capabilities = [],
        array $options = [],
    ) {
        parent::__construct($name, $capabilities, $options);
    }
}
