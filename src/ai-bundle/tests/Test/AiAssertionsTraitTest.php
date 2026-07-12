<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Test;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\TraceableToolbox;
use Symfony\AI\AiBundle\Exception\RuntimeException;
use Symfony\AI\AiBundle\Profiler\DataCollector;
use Symfony\AI\AiBundle\Test\AiAssertionsTrait;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\AI\Platform\TraceablePlatform;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The assertions of the trait, against a collector that is built by hand - the wiring to the
 * profiler of a real request is covered by the functional tests of the demo application.
 */
final class AiAssertionsTraitTest extends WebTestCase
{
    use AiAssertionsTrait;

    private static ?DataCollector $collector = null;

    protected function tearDown(): void
    {
        self::$collector = null;
    }

    public function testAssertsTheNumberOfPlatformCalls()
    {
        self::collect(models: ['gpt-4.1', 'gpt-4.1']);

        self::assertPlatformCallCount(2);
    }

    public function testFailsWhenTheNumberOfPlatformCallsDiffers()
    {
        self::collect(models: ['gpt-4.1']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that 2 platform call(s) were made, got 1: gpt-4.1.');

        self::assertPlatformCallCount(2);
    }

    public function testAssertsThatAModelWasCalled()
    {
        self::collect(models: ['text-embedding-ada-002', 'gpt-4.1']);

        self::assertModelCalled('gpt-4.1');
        self::assertModelCalled('text-embedding-ada-002');
    }

    public function testFailsWhenTheModelWasNotCalled()
    {
        self::collect(models: ['gpt-4.1']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that model "gpt-5-mini" was called, got: gpt-4.1.');

        self::assertModelCalled('gpt-5-mini');
    }

    public function testFailsWithoutAnyPlatformCall()
    {
        self::collect();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that model "gpt-4.1" was called, got: no platform call at all.');

        self::assertModelCalled('gpt-4.1');
    }

    public function testAssertsTheToolCallsOfTheModel()
    {
        self::collect(toolCalls: ['similarity_search']);

        self::assertToolCalled('similarity_search');
        self::assertToolCallCount(1);
    }

    public function testFailsWhenTheToolWasNotCalled()
    {
        self::collect(tools: ['similarity_search'], toolCalls: []);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that tool "similarity_search" was called, got: no tool call at all.');

        self::assertToolCalled('similarity_search');
    }

    public function testAssertsThatAToolIsRegistered()
    {
        self::collect(tools: ['similarity_search', 'clock']);

        self::assertToolRegistered('clock');
    }

    /**
     * A tool being registered says nothing about it being called - the collector gathers the tools
     * of every toolbox of the application, not only of the agent that handled the request.
     */
    public function testARegisteredToolIsNotACalledTool()
    {
        self::collect(tools: ['similarity_search'], toolCalls: []);

        self::assertToolRegistered('similarity_search');
        self::assertToolCallCount(0);
    }

    public function testFailsWhenTheToolIsNotRegistered()
    {
        self::collect(tools: ['clock']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that tool "movie_search" is registered, got: clock.');

        self::assertToolRegistered('movie_search');
    }

    protected static function getAiDataCollector(): DataCollector
    {
        return self::$collector ?? throw new RuntimeException('Collect data with "self::collect()" first.');
    }

    /**
     * @param list<string> $models    the models that were called on a platform
     * @param list<string> $tools     the tools that are registered on a toolbox
     * @param list<string> $toolCalls the tools the model invoked
     */
    private static function collect(array $models = [], array $tools = [], array $toolCalls = []): void
    {
        $platform = self::createStub(PlatformInterface::class);
        $platform->method('invoke')->willReturn(
            new DeferredResult(new PlainConverter(new TextResult('Answer')), self::createStub(RawResultInterface::class)),
        );

        $traceablePlatform = new TraceablePlatform($platform);
        foreach ($models as $model) {
            $traceablePlatform->invoke($model, new MessageBag(Message::ofUser('Hello')));
        }

        $toolbox = self::createStub(ToolboxInterface::class);
        $toolbox->method('getTools')->willReturn(array_map(
            static fn (string $tool): Tool => new Tool(new ExecutionReference('App\Tool\\'.$tool, '__invoke'), $tool, 'A tool.'),
            $tools,
        ));
        $toolbox->method('execute')->willReturnCallback(
            static fn (ToolCall $toolCall): ToolResult => new ToolResult($toolCall, 'Result of '.$toolCall->getName()),
        );

        $traceableToolbox = new TraceableToolbox($toolbox);
        foreach ($toolCalls as $index => $tool) {
            $traceableToolbox->execute(new ToolCall('call_'.$index, $tool));
        }

        self::$collector = new DataCollector([$traceablePlatform], [$traceableToolbox], [], [], [], []);
        self::$collector->lateCollect();
    }
}
