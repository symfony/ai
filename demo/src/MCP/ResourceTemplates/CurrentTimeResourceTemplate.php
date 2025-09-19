<?php

namespace App\MCP\ResourceTemplates;

use Mcp\Capability\Attribute\McpResourceTemplate;

class CurrentTimeResourceTemplate
{
    #[McpResourceTemplate(uriTemplate: 'time://{timezone}', name: 'time-by-timezone')]
    public function getTimeByTimezone(string $timezone): array
    {
        try {
            $time = (new \DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s T');
        } catch (\Exception $e) {
            $time = 'Invalid timezone: ' . $timezone;
        }

        return [
            'uri' => "time://$timezone",
            'mimeType' => 'text/plain',
            'text' => $time
        ];
    }
}