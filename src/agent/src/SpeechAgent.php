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

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result as ResultUpdate;
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

    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        $cancellation = new Cancellation();

        return new Execution(function () use ($input, $options, $cancellation): \Generator {
            $messages = InputNormalizer::toMessageBag($input);

            if ($this->configuration->supportsSpeechToText() && $this->speechToTextPlatform instanceof PlatformInterface) {
                $messages = $this->transcribe($messages, $options, $cancellation);
            }

            $result = null;
            foreach ($cancellation->forward($this->agent->call($messages, $options)) as $update) {
                if ($update instanceof ResultUpdate) {
                    $result = $update->getResult();

                    continue;
                }

                if ($update instanceof Progress) {
                    yield $update;
                }
            }

            if ($cancellation->isRequested()) {
                return;
            }

            if (!$result instanceof ResultInterface) {
                throw new RuntimeException(\sprintf('The agent "%s" finished without producing a result.', $this->agent->getName()));
            }

            if (!$this->textToSpeechPlatform instanceof PlatformInterface || !$this->configuration->supportsTextToSpeech()) {
                yield new ResultUpdate($result);

                return;
            }

            $speechResult = $this->textToSpeechPlatform->invoke(
                $this->configuration->getTextToSpeechModel(),
                $result->getContent(),
                $this->configuration->getTextToSpeechOptions(),
            );
            $cancellation->activate($speechResult->getRawResult());

            $speechResult->getMetadata()->add('text', $result->getContent());

            yield new ResultUpdate($speechResult->getResult());
        }, cancellation: $cancellation);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function transcribe(MessageBag $messages, array $options, Cancellation $cancellation): MessageBag
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

        $result = $this->speechToTextPlatform->invoke(
            $this->configuration->getSpeechToTextModel(),
            $audio,
            [
                ...$this->configuration->getSpeechToTextOptions(),
                ...$options,
            ],
        );
        $cancellation->activate($result->getRawResult());

        $text = new Text($result->asText());
        $messages->replace($latestUserMessage->getId(), Message::ofUser($text));

        return $messages;
    }
}
