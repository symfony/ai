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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechListener implements EventSubscriberInterface
{
    /**
     * @param SpeechPlatformInterface[] $speechPlatforms
     */
    public function __construct(private readonly iterable $speechPlatforms)
    {
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

        foreach ($this->speechPlatforms as $platform) {
            $overriddenInput = $platform->listen($input, $options);

            if (!$overriddenInput instanceof DeferredResult) {
                continue;
            }

            $inputAsText = new Text($overriddenInput->asText());

            if (!$input instanceof MessageBag) {
                $event->setInput($inputAsText);

                return;
            }

            $event->setInput(new MessageBag(
                Message::ofUser($inputAsText),
            ));
        }
    }

    public function onResult(ResultEvent $event): void
    {
        $deferredResult = $event->getDeferredResult();
        $options = $event->getOptions();

        foreach ($this->speechPlatforms as $name => $platform) {
            $payload = $platform->generate($deferredResult, $options);

            if (!$payload instanceof DeferredResult) {
                continue;
            }

            $deferredResult->addSpeech(new Speech($payload, $name));

            $event->setDeferredResult($deferredResult);
        }
    }
}
