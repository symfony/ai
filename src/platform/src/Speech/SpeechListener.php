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

use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechListener implements EventSubscriberInterface
{
    /**
     * @param PlatformInterface[]   $platforms
     * @param SpeechConfiguration[] $configurations
     */
    public function __construct(
        private readonly iterable $platforms,
        private readonly iterable $configurations,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvocationEvent::class => ['onInvocation', 255],
            ResultEvent::class => 'onResult',
        ];
    }

    public function onInvocation(InvocationEvent $event): void
    {
        $input = $event->getInput();

        $audio = ($input instanceof MessageBag && $input->containsAudio() && $input->latestAs(Role::User) instanceof UserMessage)
            ? $input->latestAs(Role::User)->getAudioContent()
            : null;

        if (!$audio instanceof Audio) {
            return;
        }

        foreach ($this->configurations as $name => $configuration) {
            if (!$configuration->supportsSpeechToText()) {
                continue;
            }

            $platform = $this->platforms[$name] ?? throw new RuntimeException(\sprintf('No platform found for configuration "%s".', $name));

            $result = $platform->invoke(
                $configuration->getOption('stt_model'),
                $audio,
                [
                    ...$configuration->getSpeechToTextOptions(),
                    ...$event->getOptions(),
                ],
            );

            $text = new Text($result->asText());

            $input instanceof MessageBag
                ? $input->replace($input->latestAs(Role::User)->getId(), Message::ofUser($text))
                : $event->setInput($text);

            break;
        }
    }

    public function onResult(ResultEvent $event): void
    {
        $deferredResult = $event->getDeferredResult();

        foreach ($this->configurations as $name => $configuration) {
            if (!$configuration->supportsTextToSpeech()) {
                continue;
            }

            $platform = $this->platforms[$name] ?? throw new RuntimeException(\sprintf('No platform found for configuration "%s".', $name));

            $speechResult = $platform->invoke(
                $configuration->getOption('tts_model'),
                $event->getInput(),
                [
                    ...$configuration->getTextToSpeechOptions(),
                    ...$event->getOptions(),
                ],
            );

            $deferredResult->addSpeech(new Speech($speechResult));
            $event->setDeferredResult($deferredResult);

            break;
        }
    }
}
