<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Speech;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechConfiguration
{
    /**
     * @param array<string, mixed> $ttsOptions
     * @param array<string, mixed> $sttOptions
     */
    public function __construct(
        private readonly ?string $ttsModel = null,
        private readonly array $ttsOptions = [],
        private readonly ?string $sttModel = null,
        private readonly array $sttOptions = [],
    ) {
    }

    public function supportsTextToSpeech(): bool
    {
        return null !== $this->ttsModel;
    }

    public function supportsSpeechToText(): bool
    {
        return null !== $this->sttModel;
    }

    public function getTextToSpeechModel(): ?string
    {
        return $this->ttsModel;
    }

    public function getSpeechToTextModel(): ?string
    {
        return $this->sttModel;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTextToSpeechOptions(): array
    {
        return $this->ttsOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSpeechToTextOptions(): array
    {
        return $this->sttOptions;
    }
}
