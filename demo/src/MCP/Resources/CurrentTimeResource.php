<?php

namespace App\MCP\Resources;

use Mcp\Capability\Attribute\McpResource;

class CurrentTimeResource
{
    #[McpResource(uri: 'time://current', name: 'current-time-resource')]
    public function getCurrentTimeResource(): array
    {
        return [
            'uri' => 'time://current',
            'mimeType' => 'text/plain',
            'text' => (new \DateTime('now'))->format('Y-m-d H:i:s T')
        ];
    }
}