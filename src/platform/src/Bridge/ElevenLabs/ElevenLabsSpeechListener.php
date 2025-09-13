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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Speech\SpeechAwarePlatformInterface;
use Symfony\AI\Platform\Speech\SpeechListenerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabsSpeechListener implements SpeechListenerInterface
{
    public function __construct(
        private readonly SpeechAwarePlatformInterface&PlatformInterface $platform,
    ) {
    }

    public function listen(object|array|string $input, array $options): Text
    {
        $speechConfiguration = $this->platform->getSpeechConfiguration();

        $input = ($input instanceof MessageBag && $input->containsAudio()) ? $input->getUserMessage()->getAudioContent() : $input;

        $result = $this->platform->invoke($speechConfiguration->sttModel, $input, $options);

        return new Text($result->asText());
    }

    public function support(object|array|string $input, array $options): bool
    {
        $speechConfiguration = $this->platform->getSpeechConfiguration();

        $model = $this->platform->getModelCatalog()->getModel($speechConfiguration->sttModel);

        return \in_array(Capability::SPEECH_TO_TEXT, $model->getCapabilities(), true);
    }
}
