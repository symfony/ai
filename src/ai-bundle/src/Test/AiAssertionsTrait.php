<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Test;

use PHPUnit\Framework\Assert;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\AiBundle\Profiler\DataCollector;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Assertions on what the AI components did while handling a request, based on what the profiler
 * collected for it - the platforms that were called, the tools that were invoked, the token usage
 * the platform reported.
 *
 * Answers of a model are not deterministic, so asserting on them is of limited use. What an agent
 * *did* is deterministic enough to assert: that it called the model, that it reached for a tool,
 * that it did not call the platform twice when once is expected.
 *
 * The profiler must be enabled for the request, which `$client->enableProfiler()` does.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-require-extends WebTestCase
 */
trait AiAssertionsTrait
{
    /**
     * Asserts the number of calls that were made to a platform, across all agents of the request.
     */
    public static function assertPlatformCallCount(int $expectedCount, string $message = ''): void
    {
        $models = self::getCalledModels();

        Assert::assertCount($expectedCount, $models, $message ?: \sprintf(
            'Failed asserting that %d platform call(s) were made, got %d: %s.',
            $expectedCount,
            \count($models),
            [] === $models ? 'none' : implode(', ', $models),
        ));
    }

    /**
     * Asserts that the given model was called at least once.
     */
    public static function assertModelCalled(string $model, string $message = ''): void
    {
        $models = self::getCalledModels();

        Assert::assertContains($model, $models, $message ?: \sprintf(
            'Failed asserting that model "%s" was called, got: %s.',
            $model,
            [] === $models ? 'no platform call at all' : implode(', ', $models),
        ));
    }

    /**
     * Asserts that the model invoked the given tool while handling the request.
     */
    public static function assertToolCalled(string $tool, string $message = ''): void
    {
        $tools = self::getCalledTools();

        Assert::assertContains($tool, $tools, $message ?: \sprintf(
            'Failed asserting that tool "%s" was called, got: %s.',
            $tool,
            [] === $tools ? 'no tool call at all' : implode(', ', $tools),
        ));
    }

    /**
     * Asserts the number of tool calls the models made while handling the request.
     */
    public static function assertToolCallCount(int $expectedCount, string $message = ''): void
    {
        $tools = self::getCalledTools();

        Assert::assertCount($expectedCount, $tools, $message ?: \sprintf(
            'Failed asserting that %d tool call(s) were made, got %d: %s.',
            $expectedCount,
            \count($tools),
            [] === $tools ? 'none' : implode(', ', $tools),
        ));
    }

    /**
     * Asserts that the given tool is registered, and therefore available to be called.
     *
     * The tools are collected from every toolbox of the application, not only from the agent that
     * handled the request - this asserts that a tool is configured, not that it was used. Use
     * `assertToolCalled()` for the latter.
     */
    public static function assertToolRegistered(string $tool, string $message = ''): void
    {
        $tools = array_map(
            static fn (Tool $registered): string => $registered->getName(),
            self::getAiDataCollector()->getTools(),
        );

        Assert::assertContains($tool, $tools, $message ?: \sprintf(
            'Failed asserting that tool "%s" is registered, got: %s.',
            $tool,
            [] === $tools ? 'no tool at all' : implode(', ', $tools),
        ));
    }

    /**
     * The data collector of the AI bundle, for the last request the client made.
     */
    protected static function getAiDataCollector(): DataCollector
    {
        $client = self::getClient();

        if (!$client instanceof KernelBrowser) {
            Assert::fail('A client is needed to make AI assertions, create one with "self::createClient()".');
        }

        $profile = $client->getProfile();

        if (!$profile instanceof Profile) {
            Assert::fail('The profiler is not enabled for the request, call "$client->enableProfiler()" before making it.');
        }

        if (!$profile->hasCollector('ai')) {
            Assert::fail('The AI data collector is not available, make sure the profiler is enabled in the "test" environment.');
        }

        $collector = $profile->getCollector('ai');

        if (!$collector instanceof DataCollector) {
            Assert::fail(\sprintf('Expected the "ai" collector to be a "%s", got a "%s".', DataCollector::class, get_debug_type($collector)));
        }

        return $collector;
    }

    /**
     * @return list<string>
     */
    private static function getCalledModels(): array
    {
        return array_map(
            static fn (array $call): string => $call['model'],
            self::getAiDataCollector()->getPlatformCalls(),
        );
    }

    /**
     * @return list<string>
     */
    private static function getCalledTools(): array
    {
        return array_map(
            static fn (ToolResult $call): string => $call->getToolCall()->getName(),
            self::getAiDataCollector()->getToolCalls(),
        );
    }
}
