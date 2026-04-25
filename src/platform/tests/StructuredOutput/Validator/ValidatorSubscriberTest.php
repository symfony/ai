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
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorResultConverter;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorSubscriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstraints;

final class ValidatorSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents()
    {
        $this->assertSame([
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
}
