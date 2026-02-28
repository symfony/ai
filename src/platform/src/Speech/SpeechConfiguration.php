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

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechConfiguration
{
    /** @var array<string, mixed> */
    private readonly array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        array $options = [],
    ) {
        if (!class_exists(OptionsResolver::class)) {
            throw new RuntimeException('For using speech, an implementation of symfony/options-resolver is required. Try running "composer require symfony/options-resolver".');
        }

        $resolver = new OptionsResolver();
        self::configureOptions($resolver);

        $this->options = $resolver->resolve($options);
    }

    public function supportsTextToSpeech(): bool
    {
        return null !== $this->options['tts_model'];
    }

    public function supportsSpeechToText(): bool
    {
        return null !== $this->options['stt_model'];
    }

    public function getTextToSpeechModel(): ?string
    {
        return $this->options['tts_model'] ?? null;
    }

    public function getSpeechToTextModel(): ?string
    {
        return $this->options['stt_model'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOption(string $key, mixed $default = null): string|array|null
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTextToSpeechOptions(): array
    {
        return $this->options['tts_options'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSpeechToTextOptions(): array
    {
        return $this->options['stt_options'];
    }

    private static function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->define('tts_model')
                ->default(null)
                ->allowedTypes('string', 'null')
            ->define('tts_options')
                ->default([])
                ->allowedTypes('array')
            ->define('stt_model')
                ->default(null)
                ->allowedTypes('string', 'null')
            ->define('stt_options')
                ->default([])
                ->allowedTypes('array')
        ;
    }
}
