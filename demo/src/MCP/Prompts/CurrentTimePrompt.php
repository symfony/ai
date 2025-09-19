<?php

namespace App\MCP\Prompts;

use Mcp\Capability\Attribute\McpPrompt;

class CurrentTimePrompt
{
    #[McpPrompt(name: 'time-analysis')]
    public function getTimeAnalysisPrompt(): array
    {
        return [
            [
                'role' => 'user',
                'content' => 'You are a time management expert. Analyze what time of day it is and suggest appropriate activities for this time.'
            ]
        ];
    }
}