<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Formats MCP profiler data for AI consumption.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<DataCollectorInterface>
 */
final class McpCollectorFormatter implements CollectorFormatterInterface
{
    use ExtractsCollectorDataTrait;

    public function getName(): string
    {
        return 'mcp';
    }

    public function format(DataCollectorInterface $collector): array
    {
        $data = $this->extractCollectorData($collector);

        $tools = $this->normalizeEntries(\is_array($data['tools'] ?? null) ? $data['tools'] : []);
        $prompts = $this->normalizeEntries(\is_array($data['prompts'] ?? null) ? $data['prompts'] : []);
        $resources = $this->normalizeEntries(\is_array($data['resources'] ?? null) ? $data['resources'] : []);
        $resourceTemplates = $this->normalizeEntries(\is_array($data['resourceTemplates'] ?? null) ? $data['resourceTemplates'] : []);

        return [
            'tool_count' => \count($tools),
            'prompt_count' => \count($prompts),
            'resource_count' => \count($resources),
            'resource_template_count' => \count($resourceTemplates),
            'total_count' => \count($tools) + \count($prompts) + \count($resources) + \count($resourceTemplates),
            'tools' => $tools,
            'prompts' => $prompts,
            'resources' => $resources,
            'resource_templates' => $resourceTemplates,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        $data = $this->extractCollectorData($collector);

        $toolCount = \count(\is_array($data['tools'] ?? null) ? $data['tools'] : []);
        $promptCount = \count(\is_array($data['prompts'] ?? null) ? $data['prompts'] : []);
        $resourceCount = \count(\is_array($data['resources'] ?? null) ? $data['resources'] : []);
        $resourceTemplateCount = \count(\is_array($data['resourceTemplates'] ?? null) ? $data['resourceTemplates'] : []);

        return [
            'tool_count' => $toolCount,
            'prompt_count' => $promptCount,
            'resource_count' => $resourceCount,
            'resource_template_count' => $resourceTemplateCount,
            'total_count' => $toolCount + $promptCount + $resourceCount + $resourceTemplateCount,
        ];
    }

    /**
     * @param array<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeEntries(array $entries): array
    {
        return array_values(array_map(fn (array $entry): array => $this->normalizeEntry($entry), $entries));
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function normalizeEntry(array $entry): array
    {
        $normalized = [];

        foreach ($entry as $key => $value) {
            $normalized[$this->toSnakeCase($key)] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }

        if (\is_array($value)) {
            if (array_is_list($value)) {
                return array_values(array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value));
            }

            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return [
            'type' => 'object',
            'class' => $value::class,
        ];
    }

    private function toSnakeCase(string $value): string
    {
        $snakeCase = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        return strtolower($snakeCase ?? $value);
    }
}
