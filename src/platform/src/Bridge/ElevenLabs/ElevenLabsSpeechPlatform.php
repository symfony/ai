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

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Speech\SpeechPlatformInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabsSpeechPlatform implements PlatformInterface, SpeechPlatformInterface
{
    /**
     * @param array<string, mixed> $speechConfiguration
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private array $speechConfiguration,
    ) {
        if (!class_exists(OptionsResolver::class)) {
            throw new RuntimeException('For using elevenlabs as as speech platform, a symfony/options-resolver implementation is required. Try running "composer require symfony/options-resolver".');
        }

        $optionsResolver = new OptionsResolver();
        self::configureOptions($optionsResolver);

        $this->speechConfiguration = $optionsResolver->resolve($speechConfiguration);
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        return $this->platform->invoke($model, $input, $options);
    }

    public function generate(DeferredResult $result, array $options): ?DeferredResult
    {
        if (!\array_key_exists('tts_model', $this->speechConfiguration) || !\array_key_exists('tts_voice', $this->speechConfiguration)) {
            return null;
        }

        $payload = $result->asText();

        return $this->invoke($this->speechConfiguration['tts_model'], ['text' => $payload], [
            'voice' => $this->speechConfiguration['tts_voice'],
            ...$this->speechConfiguration['tts_options'] ?? [],
            ...$options,
        ]);
    }

    public function listen(object|array|string $input, array $options): ?DeferredResult
    {
        if (!\array_key_exists('stt_model', $this->speechConfiguration)) {
            return null;
        }

        $input = ($input instanceof MessageBag && $input->containsAudio()) ? $input->getUserMessage()->getAudioContent() : $input;

        return $this->platform->invoke($this->speechConfiguration['stt_model'], $input, [
            ...$options,
            ...$this->speechConfiguration['stt_options'] ?? [],
        ]);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }

    private static function configureOptions(OptionsResolver $options): void
    {
        $options
            ->define('tts_model')
                ->allowedTypes('string', 'null')
            ->define('tts_voice')
                ->allowedTypes('string', 'null')
            ->define('tts_options')
                ->allowedTypes('array')
            ->define('stt_model')
                ->allowedTypes('string', 'null')
            ->define('stt_options')
                ->allowedTypes('array')
        ;
    }
}
