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
     * @param SpeechProviderInterface[] $speechProviders
     * @param SpeechListenerInterface[] $speechListeners
     */
    public function __construct(
        private readonly iterable $speechProviders,
        private readonly iterable $speechListeners,
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

        foreach ($this->speechListeners as $speechListener) {
            if (!$speechListener->support($input, $options)) {
                continue;
            }

            $overriddenInput = $speechListener->listen($input, $options);

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

        foreach ($this->speechProviders as $speechProvider) {
            if (!$speechProvider->support($deferredResult, $options)) {
                continue;
            }

            $deferredResult->addSpeech($speechProvider->generate($deferredResult, $options));

            $event->setDeferredResult($deferredResult);
        }
    }
}
