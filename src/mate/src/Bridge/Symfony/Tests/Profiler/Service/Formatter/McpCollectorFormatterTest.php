<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\McpCollectorFormatter;
use Symfony\AI\McpBundle\Profiler\DataCollector as McpDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class McpCollectorFormatterTest extends TestCase
{
    private McpCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new McpCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('mcp', $this->formatter->getName());
    }

    public function testGetSummary()
    {
        $collector = $this->createMcpCollector([
            'tools' => [[], []],
            'prompts' => [[]],
            'resources' => [[]],
            'resourceTemplates' => [[], []],
        ]);

        $summary = $this->formatter->getSummary($collector);

        $this->assertSame([
            'tool_count' => 2,
            'prompt_count' => 1,
            'resource_count' => 1,
            'resource_template_count' => 2,
            'total_count' => 6,
        ], $summary);
    }

    public function testFormatNormalizesMcpCollectorData()
    {
        $collector = $this->createMcpCollector([
            'tools' => [[
                'name' => 'symfony-services',
                'description' => 'List services',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                    ],
                ],
            ]],
            'prompts' => [[
                'name' => 'review',
                'description' => 'Code review prompt',
                'arguments' => [
                    [
                        'name' => 'scope',
                        'required' => true,
                    ],
                ],
            ]],
            'resources' => [[
                'uri' => 'symfony-profiler://profile/abc123',
                'mimeType' => 'text/plain',
            ]],
            'resourceTemplates' => [[
                'uriTemplate' => 'symfony-profiler://profile/{token}',
                'mimeType' => 'text/plain',
            ]],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['tool_count']);
        $this->assertSame(1, $result['prompt_count']);
        $this->assertSame(1, $result['resource_count']);
        $this->assertSame(1, $result['resource_template_count']);
        $this->assertSame(4, $result['total_count']);

        $this->assertSame('symfony-services', $result['tools'][0]['name']);
        $this->assertArrayHasKey('input_schema', $result['tools'][0]);
        $this->assertSame('string', $result['tools'][0]['input_schema']['properties']['query']['type']);

        $this->assertSame('review', $result['prompts'][0]['name']);
        $this->assertSame('symfony-profiler://profile/abc123', $result['resources'][0]['uri']);
        $this->assertSame('text/plain', $result['resources'][0]['mime_type']);
        $this->assertSame('symfony-profiler://profile/{token}', $result['resource_templates'][0]['uri_template']);
    }

    public function testFormatReturnsEmptySectionsWhenCollectorDataIsMissing()
    {
        $collector = $this->createMcpCollector([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['tool_count']);
        $this->assertSame(0, $result['prompt_count']);
        $this->assertSame(0, $result['resource_count']);
        $this->assertSame(0, $result['resource_template_count']);
        $this->assertSame(0, $result['total_count']);
        $this->assertSame([], $result['tools']);
        $this->assertSame([], $result['prompts']);
        $this->assertSame([], $result['resources']);
        $this->assertSame([], $result['resource_templates']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createMcpCollector(array $data): McpDataCollector
    {
        $collector = (new \ReflectionClass(McpDataCollector::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DataCollector::class, 'data'))->setValue($collector, $data);

        return $collector;
    }
}
