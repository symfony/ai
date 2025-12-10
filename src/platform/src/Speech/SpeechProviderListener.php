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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechProviderListener implements EventSubscriberInterface
{
    /**
     * @param SpeechToTextPlatformInterface[] $speechToTextPlatforms
     * @param TextToSpeechPlatformInterface[] $textToSpeechPlatforms
     */
    public function __construct(
        private readonly iterable $speechToTextPlatforms,
        private readonly iterable $textToSpeechPlatforms,
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
        $options = $event->getOptions();

        foreach ($this->speechToTextPlatforms as $speechToTextPlatform) {
            $overriddenInput = $speechToTextPlatform->listen($input, $options);

            if (!$input instanceof MessageBag) {
                $event->setInput($overriddenInput);

                return;
            }

            $event->setInput(new MessageBag(
                Message::ofUser($overriddenInput),
            ));
        }
    }

    public function onResult(ResultEvent $event): void
    {
        $deferredResult = $event->getDeferredResult();
        $options = $event->getOptions();

        foreach ($this->textToSpeechPlatforms as $textToSpeechPlatform) {
            $deferredResult->addSpeech($textToSpeechPlatform->generate($deferredResult, $options));

            $event->setDeferredResult($deferredResult);
        }
    }
}
