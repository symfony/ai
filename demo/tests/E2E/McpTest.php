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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpTest extends E2ETestCase
{
    public function testTheAgentAnswersWithToolsOfARemoteMcpServer()
    {
        $this->visit('/mcp');
        $this->assertSelectorTextContains('#welcome h4', 'Remote MCP Servers');

        // The weather server needs no exact identifier looked up first.
        $this->chat('What is the weather in Berlin right now?');

        // The agent knows nothing on its own, so an answer means the remote tool ran.
        $this->assertStringContainsString('Berlin', $this->waitForBotMessage());

        $panel = $this->openAiPanel(platformCalls: 2);

        $panel->assertMetrics(platformCalls: 2, toolCalls: 1);
        $panel->assertPlatformCall('gpt-5-mini');

        // One well-known tool per server: all three feed the same agent.
        $panel->assertToolRegistered('weather_get_weather');
        $panel->assertToolRegistered('transit_get_station_predictions');
        $panel->assertToolRegistered('livescore_get_live_scores');
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}
