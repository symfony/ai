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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechAwareInterface;
use Symfony\AI\Platform\Speech\SpeechConfiguration;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechAgent implements AgentInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly PlatformInterface $platform,
        private readonly SpeechConfiguration $configuration,
    ) {
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        if ($this->configuration->supportsSpeechToText()) {
            $messages = $this->transcribe($messages, $options);
        }

        $result = $this->agent->call($messages, $options);

        if ($this->configuration->supportsTextToSpeech() && $result instanceof SpeechAwareInterface) {
            $speechResult = $this->platform->invoke(
                $this->configuration->getTextToSpeechModel(),
                ['text' => $result->getContent()],
                $this->configuration->getTextToSpeechOptions(),
            );

            $result->addSpeech(new Speech($speechResult));
        }

        return $result;
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

        $result = $this->platform->invoke(
            $this->configuration->getSpeechToTextModel(),
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
