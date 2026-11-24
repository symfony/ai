<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;
use Symfony\AI\Platform\Event\ResultErrorEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\StructuredOutput\Serializer;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorResultConverter;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorSubscriber;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstraints;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithValidationGroups;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraints\GroupSequence;

final class ValidatorSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents()
    {
        $this->assertSame([
            InvocationEvent::class => ['processInput', 10],
            ResultEvent::class => ['processResult', -10],
        ], ValidatorSubscriber::getSubscribedEvents());
    }

    public function testProcessResultWithResponseFormat()
    {
        $subscriber = new ValidatorSubscriber();

        $model = new Model('gpt-4');

        $object = new UserWithConstraints();

        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('supports')->willReturn(true);
        $converter->method('convert')->willReturn(new ObjectResult($object));

        $options = [PlatformSubscriber::RESPONSE_FORMAT => 'SomeClass'];
        $deferred = new DeferredResult($converter, new InMemoryRawResult(), $options);
        $event = new ResultEvent($model, $deferred, $options);

        $subscriber->processResult($event);

        $newDeferred = $event->getDeferredResult();
        $this->assertInstanceOf(ValidatorResultConverter::class, $newDeferred->getResultConverter());

        $this->expectException(ValidationException::class);
        $event->getDeferredResult()->asObject();
    }

    public function testIgnoreValidationWhenNoResponseFormatSet()
    {
        $subscriber = new ValidatorSubscriber();

        $model = new Model('gpt-4');

        $object = new UserWithConstraints();

        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('supports')->willReturn(true);
        $converter->method('convert')->willReturn(new ObjectResult($object));

        $options = [];
        $deferred = new DeferredResult($converter, new InMemoryRawResult(), $options);
        $event = new ResultEvent($model, $deferred, $options);

        $subscriber->processResult($event);

        $newDeferred = $event->getDeferredResult();
        $this->assertSame($converter, $newDeferred->getResultConverter());
        $this->assertSame($object, $event->getDeferredResult()->asObject());
    }

    public function testStreamingPartialsAreNotValidated()
    {
        $stream = $this->buildStreamWithListener([
            '{"id":1,"name":"',  // partial: name still empty → would violate NotBlank
            'Alice"}',
        ]);

        // Wrap the converter chain like the real subscribers do.
        $deferred = new DeferredResult(
            new ValidatorResultConverter(new PlainConverter($stream), $this->createValidator()),
            new InMemoryRawResult(),
            [PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class],
        );

        // Trigger converter chain (which injects the validator into the listener).
        $deferred->getResult();

        $partials = iterator_to_array($deferred->asStreamedObject(), false);

        // Partials must be emitted regardless of whether they would individually fail validation.
        $this->assertNotEmpty($partials);
        foreach ($partials as $object) {
            $this->assertInstanceOf(UserWithConstraints::class, $object);
        }
    }

    public function testStreamingFinalObjectIsValidated()
    {
        $stream = $this->buildStreamWithListener([
            '{"id":1,"name":""}',  // final violates NotBlank
        ]);

        $deferred = new DeferredResult(
            new ValidatorResultConverter(new PlainConverter($stream), $this->createValidator()),
            new InMemoryRawResult(),
            [PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class],
        );

        $deferred->getResult();

        $this->expectException(ValidationException::class);

        $deferred->asObject();
    }

    public function testStreamingFinalObjectIsValidatedAgainstTheGivenGroups()
    {
        $stream = $this->buildStreamWithListener([
            '{"id":1,"name":""}',  // violates NotBlank, which is in the Default group only
        ]);

        $deferred = new DeferredResult(
            new ValidatorResultConverter(new PlainConverter($stream), $this->createValidator(), ['strict']),
            new InMemoryRawResult(),
            [PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class],
        );

        $deferred->getResult();

        $this->assertInstanceOf(UserWithConstraints::class, $deferred->asObject());
    }

    public function testProcessInputKeepsOwnOptionsFromReachingTheProvider()
    {
        $subscriber = new ValidatorSubscriber();

        $event = new InvocationEvent(new Model('gpt-4'), new MessageBag(), [
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
            ValidatorSubscriber::VALIDATE_RESPONSE => ['strict'],
            ValidatorSubscriber::VALIDATION_RETRIES => 2,
            'temperature' => 0.5,
        ]);

        $subscriber->processInput($event);

        $this->assertSame([
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
            'temperature' => 0.5,
        ], $event->getOptions());
    }

    public function testProcessInputRemovesOwnOptionsWithoutResponseFormat()
    {
        $subscriber = new ValidatorSubscriber();

        $event = new InvocationEvent(new Model('gpt-4'), new MessageBag(), [
            ValidatorSubscriber::VALIDATE_RESPONSE => true,
            ValidatorSubscriber::VALIDATION_RETRIES => 2,
            'temperature' => 0.5,
        ]);

        $subscriber->processInput($event);

        $this->assertSame(['temperature' => 0.5], $event->getOptions());
    }

    public function testProcessInputLeavesOtherOptionsUntouched()
    {
        $subscriber = new ValidatorSubscriber();

        $options = ['temperature' => 0.5];
        $event = new InvocationEvent(new Model('gpt-4'), new MessageBag(), $options);

        $subscriber->processInput($event);

        $this->assertSame($options, $event->getOptions());
    }

    public function testNegativeRetriesAreRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of retries must not be negative, -1 given.');

        new ValidatorSubscriber(retries: -1);
    }

    public function testValidationCanBeDisabled()
    {
        $invalidUser = new UserWithConstraints();

        $event = $this->createResultEvent($invalidUser, new ValidatorSubscriber(), [
            ValidatorSubscriber::VALIDATE_RESPONSE => false,
        ]);

        $this->assertSame($invalidUser, $event->getDeferredResult()->asObject());
    }

    public function testValidationGroupsLimitTheConstraintsBeingChecked()
    {
        $user = new UserWithValidationGroups();
        $user->age = 1;

        // "name" is blank, but NotBlank is in the Default group only.
        $event = $this->createResultEvent($user, new ValidatorSubscriber(), [
            ValidatorSubscriber::VALIDATE_RESPONSE => ['strict'],
        ]);

        $this->assertSame($user, $event->getDeferredResult()->asObject());
    }

    public function testValidationGroupsAreEnforced()
    {
        $user = new UserWithValidationGroups();
        $user->name = 'Alice';

        // "age" is zero, and Positive is in the "strict" group.
        $event = $this->createResultEvent($user, new ValidatorSubscriber(), [
            ValidatorSubscriber::VALIDATE_RESPONSE => new GroupSequence(['Default', 'strict']),
        ]);

        $this->expectException(ValidationException::class);

        $event->getDeferredResult()->asObject();
    }

    public function testInvalidValidateResponseOptionIsRejectedBeforeTheRequest()
    {
        $calls = [];
        $provider = $this->createProvider($calls, ['{"id":1,"name":"Alice"}']);

        try {
            $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
                PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
                ValidatorSubscriber::VALIDATE_RESPONSE => 1,
            ]);
            $this->fail(\sprintf('Expected a "%s" to be thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The "validate_response" option must be a boolean, a validation group, a list of validation groups or an instance of "Symfony\\Component\\Validator\\Constraints\\GroupSequence", "int" given.', $e->getMessage());
            $this->assertCount(0, $calls);
        }
    }

    public function testInvalidValidationRetriesOptionIsRejectedBeforeTheRequest()
    {
        $calls = [];
        $provider = $this->createProvider($calls, ['{"id":1,"name":"Alice"}']);

        try {
            $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
                PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
                ValidatorSubscriber::VALIDATION_RETRIES => 'two',
            ]);
            $this->fail(\sprintf('Expected a "%s" to be thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The "validation_retries" option must be a non-negative integer, "string" given.', $e->getMessage());
            $this->assertCount(0, $calls);
        }
    }

    public function testNegativeValidationRetriesOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "validation_retries" option must be a non-negative integer, -1 given.');

        $this->createResultEvent(new UserWithConstraints(), new ValidatorSubscriber(), [
            ValidatorSubscriber::VALIDATION_RETRIES => -1,
        ]);
    }

    public function testRetryHandsTheViolationsBackToTheModel()
    {
        $calls = [];
        $provider = $this->createProvider($calls, [
            '{"id":-1,"name":""}',
            '{"id":1,"name":"Alice"}',
        ]);

        $user = $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
            ValidatorSubscriber::VALIDATION_RETRIES => 1,
        ])->asObject(UserWithConstraints::class);

        $this->assertSame('Alice', $user->name);
        $this->assertCount(2, $calls);

        $retryMessages = $calls[1]->getMessages();
        $this->assertCount(3, $retryMessages);
        $this->assertSame('{"id":-1,"name":""}', $this->textOf($retryMessages[1]));

        $correction = $this->textOf($retryMessages[2]);
        $this->assertStringContainsString('did not pass validation', $correction);
        $this->assertStringContainsString('id:', $correction);
        $this->assertStringContainsString('name:', $correction);
    }

    public function testRetriesAreBounded()
    {
        $calls = [];
        $provider = $this->createProvider($calls, [
            '{"id":-1,"name":""}',
            '{"id":-2,"name":""}',
            '{"id":-3,"name":""}',
        ]);

        $deferred = $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
            ValidatorSubscriber::VALIDATION_RETRIES => 2,
        ]);

        try {
            $deferred->asObject();
            $this->fail(\sprintf('Expected a "%s" to be thrown.', ValidationException::class));
        } catch (ValidationException) {
            $this->assertCount(3, $calls);
        }
    }

    public function testRetryDispatchesTheResultConvertedEventOnce()
    {
        $calls = [];
        $converted = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ResultConvertedEvent::class, static function (ResultConvertedEvent $event) use (&$converted): void {
            $converted[] = $event->getResult();
        });

        $provider = $this->createProvider($calls, [
            '{"id":-1,"name":""}',
            '{"id":1,"name":"Alice"}',
        ], dispatcher: $dispatcher);

        $user = $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
            ValidatorSubscriber::VALIDATION_RETRIES => 1,
        ])->asObject(UserWithConstraints::class);

        $this->assertCount(1, $converted);
        $this->assertSame($user, $converted[0]->getContent());
    }

    public function testFailedRetriesDispatchTheResultErrorEventOnce()
    {
        $calls = [];
        $errors = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ResultErrorEvent::class, static function (ResultErrorEvent $event) use (&$errors): void {
            $errors[] = $event->getError();
        });

        $provider = $this->createProvider($calls, [
            '{"id":-1,"name":""}',
            '{"id":-2,"name":""}',
        ], dispatcher: $dispatcher);

        $deferred = $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
            ValidatorSubscriber::VALIDATION_RETRIES => 1,
        ]);

        try {
            $deferred->asObject();
            $this->fail(\sprintf('Expected a "%s" to be thrown.', ValidationException::class));
        } catch (ValidationException) {
            $this->assertCount(2, $calls);
            $this->assertCount(1, $errors);
        }
    }

    public function testNoRetryHappensByDefault()
    {
        $calls = [];
        $provider = $this->createProvider($calls, [
            '{"id":-1,"name":""}',
            '{"id":1,"name":"Alice"}',
        ]);

        $deferred = $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
        ]);

        try {
            $deferred->asObject();
            $this->fail(\sprintf('Expected a "%s" to be thrown.', ValidationException::class));
        } catch (ValidationException) {
            $this->assertCount(1, $calls);
        }
    }

    public function testRetryCountCanBeConfiguredOnTheSubscriber()
    {
        $calls = [];
        $provider = $this->createProvider($calls, [
            '{"id":-1,"name":""}',
            '{"id":1,"name":"Alice"}',
        ], new ValidatorSubscriber(retries: 1));

        $user = $provider->invoke('gpt-4o', new MessageBag(Message::ofUser('Who is the user?')), [
            PlatformSubscriber::RESPONSE_FORMAT => UserWithConstraints::class,
        ])->asObject(UserWithConstraints::class);

        $this->assertSame('Alice', $user->name);
        $this->assertCount(2, $calls);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createResultEvent(object $object, ValidatorSubscriber $subscriber, array $options): ResultEvent
    {
        $options[PlatformSubscriber::RESPONSE_FORMAT] = $object::class;

        $model = new Model('gpt-4');
        $invocation = new InvocationEvent($model, new MessageBag(), $options);
        $subscriber->processInput($invocation);

        $converter = $this->createStub(ResultConverterInterface::class);
        $converter->method('supports')->willReturn(true);
        $converter->method('convert')->willReturn(new ObjectResult($object));

        $event = new ResultEvent($model, new DeferredResult($converter, new InMemoryRawResult(), $invocation->getOptions()), $invocation->getOptions());
        $subscriber->processResult($event);

        return $event;
    }

    private function textOf(MessageInterface $message): string
    {
        $content = $message->getContent();

        $this->assertIsArray($content);
        $this->assertInstanceOf(Text::class, $content[0]);

        return $content[0]->getText();
    }

    /**
     * @param list<MessageBag> $calls
     * @param list<string>     $answers
     */
    private function createProvider(array &$calls, array $answers, ?ValidatorSubscriber $subscriber = null, EventDispatcher $dispatcher = new EventDispatcher()): ProviderInterface
    {
        $dispatcher->addSubscriber(new PlatformSubscriber());
        $dispatcher->addSubscriber($subscriber ?? new ValidatorSubscriber());

        // The model client only sees the normalized payload, so record the inputs on the event instead.
        $dispatcher->addListener(InvocationEvent::class, static function (InvocationEvent $event) use (&$calls): void {
            $calls[] = $event->getInput();
        }, -100);

        return MockPlatformFactory::createProvider(
            static function () use (&$calls, $answers): string {
                return $answers[\count($calls) - 1];
            },
            eventDispatcher: $dispatcher,
        );
    }

    /**
     * @param string[] $chunks
     */
    private function buildStreamWithListener(array $chunks): StreamResult
    {
        $generator = (static function () use ($chunks): \Generator {
            foreach ($chunks as $chunk) {
                yield new TextDelta($chunk);
            }
        })();

        return new StreamResult(
            $generator,
            [new PartialObjectStreamListener(new Serializer(), UserWithConstraints::class)],
        );
    }

    private function createValidator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        return \Symfony\Component\Validator\Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
