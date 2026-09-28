<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;
use Symfony\AI\Platform\Event\ResultErrorEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\EventDispatcher\EventDispatcher;

class InMemoryPlatformTest extends TestCase
{
    public function testPlatformInvokeWithFixedResult()
    {
        $platform = new InMemoryPlatform('Mocked result');
        $result = $platform->invoke('test', 'input');

        $this->assertSame('Mocked result', $result->asText());
        $this->assertSame('Mocked result', $result->getResult()->getContent());
        $this->assertSame(['text' => 'Mocked result'], $result->getRawResult()->getData());
    }

    public function testPlatformInvokeWithCallableResult()
    {
        $platform = new InMemoryPlatform(static function (Model $model, $input) {
            return strtoupper((string) $input);
        });

        $result = $platform->invoke('test', 'dynamic text');

        $this->assertSame('DYNAMIC TEXT', $result->asText());
    }

    public function testPlatformInvokeWithVectorResultResponse()
    {
        $platform = new InMemoryPlatform(
            static fn () => new VectorResult([new Vector([0.1, 0.1, 0.5])])
        );

        $result = $platform->invoke('test', 'dynamic text');

        $this->assertEquals([0.1, 0.1, 0.5], $result->asVectors()[0]->getData());
    }

    public function testWithoutADispatcherNoEventsAreDispatched()
    {
        $platform = new InMemoryPlatform('Mocked result');
        $result = $platform->invoke('test', 'input');

        // Nothing to assert on the dispatcher side; this only has to not throw or behave
        // differently without one.
        $this->assertSame('Mocked result', $result->asText());
    }

    public function testADispatcherReceivesTheInvocationAndResultEvents()
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        $dispatcher->addListener(InvocationEvent::class, static function (InvocationEvent $event) use (&$seen): void {
            $seen[] = $event;
        });
        $dispatcher->addListener(ResultEvent::class, static function (ResultEvent $event) use (&$seen): void {
            $seen[] = $event;
        });
        $platform = new InMemoryPlatform('Mocked result', $dispatcher);

        $platform->invoke('test', 'input');

        $this->assertCount(2, $seen);
        $this->assertInstanceOf(InvocationEvent::class, $seen[0]);
        $this->assertSame('test', $seen[0]->getModel()->getName());
        $this->assertInstanceOf(ResultEvent::class, $seen[1]);
    }

    public function testADispatcherReceivesResultConvertedOnceTheDeferredResultIsRead()
    {
        $dispatcher = new EventDispatcher();
        $converted = [];
        $dispatcher->addListener(ResultConvertedEvent::class, static function (ResultConvertedEvent $event) use (&$converted): void {
            $converted[] = $event;
        });
        $platform = new InMemoryPlatform('Mocked result', $dispatcher);

        $deferred = $platform->invoke('test', 'input');
        $this->assertSame([], $converted, 'not dispatched before the deferred result is read');

        $deferred->getResult();

        $this->assertCount(1, $converted);
        $this->assertSame('Mocked result', $converted[0]->getResult()->getContent());
    }

    public function testADispatcherReceivesResultErrorWhenTheScriptThrows()
    {
        $dispatcher = new EventDispatcher();
        $errors = [];
        $dispatcher->addListener(ResultErrorEvent::class, static function (ResultErrorEvent $event) use (&$errors): void {
            $errors[] = $event;
        });
        $platform = new InMemoryPlatform(static function (): never {
            throw new RuntimeException('Boom');
        }, $dispatcher);

        try {
            $platform->invoke('test', 'input');
            $this->fail('Expected the original exception to propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Boom', $exception->getMessage());
        }

        $this->assertCount(1, $errors);
        $this->assertSame('Boom', $errors[0]->getError()->getMessage());
    }
}
