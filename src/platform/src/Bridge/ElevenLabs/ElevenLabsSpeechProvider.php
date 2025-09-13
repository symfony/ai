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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechAwarePlatformInterface;
use Symfony\AI\Platform\Speech\SpeechProviderInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabsSpeechProvider implements SpeechProviderInterface
{
    public function __construct(
        private readonly SpeechAwarePlatformInterface&PlatformInterface $platform,
    ) {
    }

    public function generate(DeferredResult $result, array $options): Speech
    {
        $speechConfiguration = $this->platform->getSpeechConfiguration();

        $payload = $result->asText();

        $speechResult = $this->platform->invoke($speechConfiguration->ttsModel, ['text' => $payload], [
            'voice' => $speechConfiguration->ttsVoice,
            ...$speechConfiguration->ttsExtraOptions,
            ...$options,
        ]);

        return new Speech($payload, $speechResult, 'elevenlabs');
    }

    public function support(DeferredResult $result, array $options): bool
    {
        $speechConfiguration = $this->platform->getSpeechConfiguration();

        $model = $this->platform->getModelCatalog()->getModel($speechConfiguration->ttsModel);

        return \in_array(Capability::TEXT_TO_SPEECH, $model->getCapabilities(), true);
    }
}
