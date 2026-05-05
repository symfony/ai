<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput\Validator;

use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ValidatorSubscriber implements EventSubscriberInterface
{
    private readonly ValidatorInterface $validator;

    public function __construct(
        ?ValidatorInterface $validator = null,
    ) {
        $this->validator = $validator ?? Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ResultEvent::class => ['processResult', -10],
        ];
    }

    public function processResult(ResultEvent $event): void
    {
        $options = $event->getOptions();

        if (!isset($options[PlatformSubscriber::RESPONSE_FORMAT])) {
            return;
        }

        $deferred = $event->getDeferredResult();
        $converter = new ValidatorResultConverter(
            $deferred->getResultConverter(),
            $this->validator,
        );

        $event->setDeferredResult(new DeferredResult($converter, $deferred->getRawResult(), $options));
    }
}
