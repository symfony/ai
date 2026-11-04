<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;

final class ResultEventTest extends TestCase
{
    public function testGettersReturnConstructorValues()
    {
        $model = new Model('test-model', [Capability::OUTPUT_TEXT]);
        $deferredResult = $this->createDeferredResult();
        $options = ['temperature' => 0.7];
        $provider = $this->createStub(ProviderInterface::class);

        $event = new ResultEvent($model, $deferredResult, $options, 'Hello?', $provider);

        $this->assertSame($model, $event->getModel());
        $this->assertSame($deferredResult, $event->getDeferredResult());
        $this->assertSame($options, $event->getOptions());
        $this->assertSame('Hello?', $event->getInput());
        $this->assertSame($provider, $event->getProvider());
    }

    public function testSetDeferredResultOverridesDeferredResult()
    {
        $event = new ResultEvent(new Model('test-model', [Capability::OUTPUT_TEXT]), $this->createDeferredResult());

        $newDeferredResult = $this->createDeferredResult();
        $event->setDeferredResult($newDeferredResult);

        $this->assertSame($newDeferredResult, $event->getDeferredResult());
    }

    public function testProviderIsOptional()
    {
        $model = new Model('test-model', [Capability::OUTPUT_TEXT]);

        $this->assertNull((new ResultEvent($model, $this->createDeferredResult()))->getProvider());
    }

    private function createDeferredResult(): DeferredResult
    {
        return new DeferredResult($this->createStub(ResultConverterInterface::class), $this->createStub(RawResultInterface::class));
    }
}
