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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ValidatorSubscriber implements EventSubscriberInterface
{
    /**
     * Whether to validate the structured output, and against which groups: a boolean, a group,
     * a list of groups or a group sequence. Defaults to validating the "Default" group.
     */
    public const VALIDATE_RESPONSE = 'validate_response';

    /**
     * How often to hand the violations back to the model and let it answer again.
     */
    public const VALIDATION_RETRIES = 'validation_retries';

    private readonly ValidatorInterface $validator;

    /**
     * The options of the running invocation as the caller passed them, so a retry can be invoked
     * the same way - the ones on the result event have the response format turned into a schema.
     *
     * @var array<string, mixed>|null
     */
    private ?array $invocationOptions = null;

    public function __construct(
        ?ValidatorInterface $validator = null,
        private readonly int $retries = 0,
        private readonly ViolationFormatterInterface $violationFormatter = new ViolationFormatter(),
    ) {
        if ($retries < 0) {
            throw new InvalidArgumentException(\sprintf('The number of retries must not be negative, %d given.', $retries));
        }

        $this->validator = $validator ?? Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvocationEvent::class => ['processInput', 10],
            ResultEvent::class => ['processResult', -10],
        ];
    }

    public function processInput(InvocationEvent $event): void
    {
        $this->invocationOptions = null;

        $options = $event->getOptions();

        // Checked before the request is sent, so an invalid option costs no call to the model.
        if (false !== $validate = $options[self::VALIDATE_RESPONSE] ?? true) {
            $this->resolveGroups($validate);
        }
        $this->resolveRetries($options);

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT])) {
            $this->invocationOptions = $options;
        }

        if (!\array_key_exists(self::VALIDATE_RESPONSE, $options) && !\array_key_exists(self::VALIDATION_RETRIES, $options)) {
            return;
        }

        // Both options are consumed here, providers would forward them to the API as request body fields.
        unset($options[self::VALIDATE_RESPONSE], $options[self::VALIDATION_RETRIES]);

        $event->setOptions($options);
    }

    public function processResult(ResultEvent $event): void
    {
        $invocationOptions = $this->invocationOptions;
        $this->invocationOptions = null;

        $options = $event->getOptions();

        if (!isset($options[PlatformSubscriber::RESPONSE_FORMAT])) {
            return;
        }

        $validate = $invocationOptions[self::VALIDATE_RESPONSE] ?? true;

        if (false === $validate) {
            return;
        }

        $deferred = $event->getDeferredResult();
        $converter = new ValidatorResultConverter(
            $deferred->getResultConverter(),
            $this->validator,
            $this->resolveGroups($validate),
            $this->createRetry($event, $invocationOptions),
        );

        $event->setDeferredResult(new DeferredResult($converter, $deferred->getRawResult(), $options));
    }

    /**
     * @return string|list<string>|GroupSequence|null
     */
    private function resolveGroups(mixed $validate): string|array|GroupSequence|null
    {
        if (true === $validate) {
            return null;
        }

        if (\is_string($validate) || \is_array($validate) || $validate instanceof GroupSequence) {
            return $validate;
        }

        throw new InvalidArgumentException(\sprintf('The "%s" option must be a boolean, a validation group, a list of validation groups or an instance of "%s", "%s" given.', self::VALIDATE_RESPONSE, GroupSequence::class, get_debug_type($validate)));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveRetries(array $options): int
    {
        $retries = $options[self::VALIDATION_RETRIES] ?? $this->retries;

        if (\is_int($retries) && $retries >= 0) {
            return $retries;
        }

        if (\is_int($retries)) {
            throw new InvalidArgumentException(\sprintf('The "%s" option must be a non-negative integer, %d given.', self::VALIDATION_RETRIES, $retries));
        }

        throw new InvalidArgumentException(\sprintf('The "%s" option must be a non-negative integer, "%s" given.', self::VALIDATION_RETRIES, get_debug_type($retries)));
    }

    /**
     * Builds the callback that asks the model to answer again, with its rejected answer and the
     * violations appended to the conversation. Returns null when no retry is possible or wanted.
     *
     * @param array<string, mixed>|null $invocationOptions
     *
     * @return (\Closure(ConstraintViolationListInterface, string|null): ResultInterface)|null
     */
    private function createRetry(ResultEvent $event, ?array $invocationOptions): ?\Closure
    {
        if (null === $invocationOptions) {
            return null;
        }

        $retries = $this->resolveRetries($invocationOptions);

        if (0 === $retries) {
            return null;
        }

        $input = $event->getInput();
        $provider = $event->getProvider();

        // A retry continues the conversation, so there has to be one, and a provider to continue it with.
        if (!$input instanceof MessageBag) {
            return null;
        }

        if (null === $provider) {
            return null;
        }

        $model = $event->getModel();
        $formatter = $this->violationFormatter;
        $invocationOptions[self::VALIDATION_RETRIES] = $retries - 1;

        return static function (ConstraintViolationListInterface $violations, ?string $text) use ($provider, $model, $input, $invocationOptions, $formatter): ResultInterface {
            if (null !== $text) {
                $input = $input->with(Message::ofAssistant($text));
            }

            $input = $input->with(Message::ofUser($formatter->format($violations)));

            $retry = $provider->invoke($model, $input, $invocationOptions);

            // Converted without the callbacks of the retry, so the result and error events fire once, for the outer invocation.
            return (new DeferredResult($retry->getResultConverter(), $retry->getRawResult(), $retry->getOptions()))->getResult();
        };
    }
}
