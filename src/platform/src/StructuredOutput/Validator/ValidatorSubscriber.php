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

use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ValidatorSubscriber implements EventSubscriberInterface
{
    public const VALIDATION_GROUPS = 'validation_groups';

    private readonly ValidatorInterface $validator;

    /** @var string|GroupSequence|array<string|GroupSequence>|null */
    private string|GroupSequence|array|null $invocationGroups = null;

    /**
     * @param string|GroupSequence|array<string|GroupSequence>|null $groups The validation groups to validate the structured output in unless the "validation_groups" option is passed, or null for the validator's default group
     */
    public function __construct(
        ?ValidatorInterface $validator = null,
        private readonly string|GroupSequence|array|null $groups = null,
    ) {
        $this->validator = $validator ?? Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvocationEvent::class => 'processInput',
            ResultEvent::class => ['processResult', -10],
        ];
    }

    public function processInput(InvocationEvent $event): void
    {
        $options = $event->getOptions();
        $this->invocationGroups = null;

        if (!\array_key_exists(self::VALIDATION_GROUPS, $options)) {
            return;
        }

        $groups = $options[self::VALIDATION_GROUPS];

        if (null !== $groups && !\is_string($groups) && !\is_array($groups) && !$groups instanceof GroupSequence) {
            throw new InvalidArgumentException('The "validation_groups" option must be a string, an array or a GroupSequence.');
        }

        $this->invocationGroups = $groups;

        // Consume the option, so it is not forwarded to the provider
        unset($options[self::VALIDATION_GROUPS]);
        $event->setOptions($options);
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
            $this->invocationGroups ?? $this->groups,
        );

        $event->setDeferredResult(new DeferredResult($converter, $deferred->getRawResult(), $options));
    }
}
