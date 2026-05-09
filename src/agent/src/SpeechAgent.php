<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Speech\SpeechConfiguration;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechAgent implements AgentInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly SpeechConfiguration $configuration,
        private readonly ?PlatformInterface $speechToTextPlatform = null,
        private readonly ?PlatformInterface $textToSpeechPlatform = null,
    ) {
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        if ($this->configuration->supportsSpeechToText() && $this->speechToTextPlatform instanceof PlatformInterface) {
            $messages = $this->transcribe($messages, $options);
        }

        $result = $this->agent->call($messages, $options);

        if (!$this->textToSpeechPlatform instanceof PlatformInterface) {
            return $result;
        }

        $ttsModel = $this->configuration->getTextToSpeechModel();

        if (null === $ttsModel || '' === $ttsModel) {
            return $result;
        }

        $content = $result->getContent();

        if (null === $content) {
            return $result;
        }

        if (\is_iterable($content) && !\is_array($content)) {
            $content = iterator_to_array($content);
        }

        $speechResult = $this->textToSpeechPlatform->invoke(
            $ttsModel,
            $content,
            $this->configuration->getTextToSpeechOptions(),
        );

        $speechResult->getMetadata()->add('text', $content);

        return $speechResult->getResult();
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function transcribe(MessageBag $messages, array $options): MessageBag
    {
        try {
            $latestUserMessage = $messages->latestAs(Role::User);
        } catch (InvalidArgumentException) {
            return $messages;
        }

        if (!$latestUserMessage instanceof UserMessage) {
            return $messages;
        }

        if (!$latestUserMessage->hasAudioContent()) {
            return $messages;
        }

        $audio = $latestUserMessage->getAudioContent();

        \assert($this->speechToTextPlatform instanceof PlatformInterface);

        $sttModel = $this->configuration->getSpeechToTextModel();

        if (null === $sttModel || '' === $sttModel) {
            return $messages;
        }

        $result = $this->speechToTextPlatform->invoke(
            $sttModel,
            $audio,
            [
                ...$this->configuration->getSpeechToTextOptions(),
                ...$options,
            ],
        );

        $text = new Text($result->asText());
        $messages->replace($latestUserMessage->getId(), Message::ofUser($text));

        return $messages;
    }
}
