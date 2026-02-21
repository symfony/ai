<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\InputProcessor;

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechConfiguration;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechProcessor implements InputProcessorInterface, OutputProcessorInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly SpeechConfiguration $configuration,
    ) {
    }

    public function processInput(Input $input): void
    {
        if (!$this->configuration->supportsSpeechToText()) {
            return;
        }

        $messageBag = $input->getMessageBag();
        $latestUserMessage = $messageBag->latestAs(Role::User);

        if (!$latestUserMessage instanceof UserMessage) {
            return;
        }

        $audio = $latestUserMessage->getAudioContent();

        $result = $this->platform->invoke(
            $this->configuration->getSpeechToTextModel(),
            $audio,
            [
                ...$this->configuration->getSpeechToTextOptions(),
                ...$input->getOptions(),
            ],
        );

        $text = new Text($result->asText());
        $messageBag->replace($latestUserMessage->getId(), Message::ofUser($text));
    }

    public function processOutput(Output $output): void
    {
        if (!$this->configuration->supportsTextToSpeech()) {
            return;
        }

        $result = $output->getResult();

        $speechResult = $this->platform->invoke(
            $this->configuration->getTextToSpeechModel(),
            ['text' => $result->getContent()],
            $this->configuration->getTextToSpeechOptions(),
        );

        $result->addSpeech(new Speech($speechResult));
    }
}
