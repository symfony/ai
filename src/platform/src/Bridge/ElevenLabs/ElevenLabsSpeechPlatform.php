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

use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechToTextPlatformInterface;
use Symfony\AI\Platform\Speech\TextToSpeechPlatformInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabsSpeechPlatform implements PlatformInterface, TextToSpeechPlatformInterface, SpeechToTextPlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $ttsModel,
        private readonly string $ttsVoice,
        private readonly string $sttModel,
        private readonly array $ttsOptions = [],
        private readonly array $sttOptions = [],
    ) {
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        return $this->platform->invoke($model, $input, $options);
    }

    public function generate(DeferredResult $result, array $options): Speech
    {
        $payload = $result->asText();

        $speechResult = $this->invoke($this->ttsModel, ['text' => $payload], [
            'voice' => $this->ttsVoice,
            ...$this->ttsOptions,
            ...$options,
        ]);

        return new Speech($payload, $speechResult, 'elevenlabs');
    }

    public function listen(object|array|string $input, array $options): Text
    {
        $input = ($input instanceof MessageBag && $input->containsAudio()) ? $input->getUserMessage()->getAudioContent() : $input;

        $result = $this->platform->invoke($this->sttModel, $input, [
            ...$options,
            ...$this->sttOptions,
        ]);

        return new Text($result->asText());
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}
