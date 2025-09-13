<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Speech;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechConfiguration
{
    /**
     * @param array<string, mixed> $ttsExtraOptions
     * @param array<string, mixed> $sttExtraOptions
     */
    public function __construct(
        public readonly ?string $ttsModel = null,
        public readonly ?string $ttsVoice = null,
        public readonly array $ttsExtraOptions = [],
        public readonly ?string $sttModel = null,
        public readonly array $sttExtraOptions = [],
    ) {
    }
}
