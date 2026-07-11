<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\E2E;

use PHPUnit\Framework\Assert;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;

/**
 * The Symfony AI panel of the web profiler, for the last request handled by the application.
 *
 * Reading the panel means scraping the template of the AI bundle, `@Ai/data_collector.html.twig`,
 * because Panther drives the application in a web server of its own: the test process cannot reach
 * the data collector through the container, only what it renders. Keeping the scraping in one place
 * puts a single seam between these tests and the markup of the bundle.
 *
 * Applications that test their agents with a `WebTestCase` should not do any of this, and use the
 * `AiAssertionsTrait` of the AI bundle instead - it asserts on the data collector, which hands back
 * the input as a `MessageBag` and the token usage as `Metadata`, rather than the strings the panel
 * happens to render. `App\Tests\AiAssertionsTest` uses it; only these browser tests cannot.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AiPanel
{
    private function __construct(
        private readonly Crawler $crawler,
    ) {
    }

    /**
     * Opens the panel for the last request that hit the profiler - the live component or streaming
     * request that called the agent.
     *
     * The profile of a streamed answer is only written once the server terminated the request, so
     * the browser sees the last chunk of an answer before its profile is available. Waiting for the
     * expected number of platform calls bridges that gap.
     */
    public static function openLatest(Client $client, int $platformCalls = 1): self
    {
        $panel = new self($client->request('GET', '/_profiler/latest?panel=ai'));

        for ($attempt = 0; $attempt < 10 && \count($panel->platformCalls()) < $platformCalls; ++$attempt) {
            usleep(500_000);
            $panel = new self($client->request('GET', '/_profiler/latest?panel=ai'));
        }

        Assert::assertStringContainsString('Symfony AI', $panel->crawler->filter('#collector-content h2')->text(), 'Symfony AI panel in the profiler');
        Assert::assertNotCount(0, $panel->crawler->filter('#menu-profiler a[href*="panel=ai"]'), 'Symfony AI entry in the profiler menu');

        return $panel;
    }

    /**
     * Asserts the metrics rendered at the top of the panel.
     */
    public function assertMetrics(int $platformCalls = 1, ?int $tools = null, ?int $toolCalls = null): void
    {
        $metrics = [];
        $this->crawler->filter('#collector-content .metrics .metric')->each(static function (Crawler $metric) use (&$metrics): void {
            $metrics[$metric->filter('.label')->text()] = (int) $metric->filter('.value')->text();
        });

        $this->assertMetric($metrics, 'Platform Calls', $platformCalls);

        if (null !== $tools) {
            $this->assertMetric($metrics, 'Tools', $tools);
        }

        if (null !== $toolCalls) {
            $this->assertMetric($metrics, 'Tool Calls', $toolCalls);
        }
    }

    /**
     * Asserts that the panel lists a successful platform call with the given model, and that the
     * platform reported token usage - which the panel renders as metadata of the call's result.
     */
    public function assertPlatformCall(string $model, bool $tokenUsage = true): void
    {
        $calls = $this->platformCalls();

        foreach ($calls as $call) {
            Assert::assertStringNotContainsString('Failed:', $call['result'], \sprintf('Platform call to "%s" failed: %s', $call['model'], $call['result']));
        }

        $models = array_column($calls, 'model');
        Assert::assertContains($model, $models, \sprintf('Platform call with model "%s" in Symfony AI panel, got: %s', $model, implode(', ', $models)));

        if ($tokenUsage) {
            $results = implode("\n", array_column($calls, 'result'));
            Assert::assertStringContainsString('token_usage', $results, 'Token usage metadata in Symfony AI panel');
        }
    }

    /**
     * Asserts that the given tool is rendered in the tools table of the panel.
     *
     * The data collector gathers the tools of every toolbox of the application, not only of the
     * agent that was called - so this asserts that the tool is configured, not that it was used.
     * The tool calls of the request are the ones asserted by `assertMetrics()`.
     */
    public function assertToolRegistered(string $tool): void
    {
        $tools = [];

        // The panel renders three kinds of tables, all of them with `th` cells: the platform calls,
        // the tools, and the tool calls. Only the tools table is headed by a `Name` column.
        $this->crawler->filter('#collector-content table.table')->each(static function (Crawler $table) use (&$tools): void {
            $headers = $table->filter('thead th')->each(static fn (Crawler $cell) => $cell->text());

            if (['Name', 'Description', 'Class & Method', 'Parameters'] !== $headers) {
                return;
            }

            $tools = $table->filter('tbody th')->each(static fn (Crawler $cell) => $cell->text());
        });

        Assert::assertContains($tool, $tools, \sprintf('Tool "%s" in Symfony AI panel, got: %s', $tool, implode(', ', $tools)));
    }

    /**
     * Reads the platform calls out of the call tables of the panel.
     *
     * @return list<array{model: string, input: string, options: string, result: string}>
     */
    public function platformCalls(): array
    {
        return $this->crawler->filter('#collector-content .sf-tabs .tab-content table.table')->each(static function (Crawler $table): array {
            $call = [];
            $table->filter('tbody tr')->each(static function (Crawler $row) use (&$call): void {
                $call[strtolower($row->filter('th')->text())] = $row->filter('td')->text();
            });

            return [
                'model' => $call['model'] ?? '',
                'input' => $call['input'] ?? '',
                'options' => $call['options'] ?? '',
                'result' => $call['result'] ?? '',
            ];
        });
    }

    /**
     * A model can always decide to loop another round, so the expected counts are lower bounds -
     * except for zero, which is exact: the use case is expected to make no such call at all.
     *
     * @param array<string, int> $metrics
     */
    private function assertMetric(array $metrics, string $label, int $expected): void
    {
        $actual = $metrics[$label] ?? 0;
        $message = \sprintf('"%s" in Symfony AI panel', $label);

        if (0 === $expected) {
            Assert::assertSame(0, $actual, $message);

            return;
        }

        Assert::assertGreaterThanOrEqual($expected, $actual, $message);
    }
}
