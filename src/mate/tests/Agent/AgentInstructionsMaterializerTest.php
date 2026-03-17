<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Agent;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Agent\AgentInstructionsAggregator;
use Symfony\AI\Mate\Agent\AgentInstructionsMaterializer;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AgentInstructionsMaterializerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/mate-agent-materializer-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testMaterializeForExtensionsWritesInstructionArtifacts()
    {
        mkdir($this->tempDir.'/vendor/vendor/package-a', 0755, true);
        file_put_contents(
            $this->tempDir.'/vendor/vendor/package-a/INSTRUCTIONS.md',
            "# Package A\n\nUse package A tools."
        );

        $logger = new NullLogger();
        $aggregator = new AgentInstructionsAggregator($this->tempDir, [], $logger);
        $materializer = new AgentInstructionsMaterializer($this->tempDir, $aggregator, $logger);

        $result = $materializer->materializeForExtensions([
            '_custom' => ['dirs' => [], 'includes' => []],
            'vendor/package-a' => [
                'dirs' => [],
                'includes' => [],
                'instructions' => 'INSTRUCTIONS.md',
            ],
        ]);

        $this->assertTrue($result['instructions_file_updated']);
        $this->assertTrue($result['agents_file_updated']);
        $this->assertFileExists($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertFileExists($this->tempDir.'/AGENTS.md');

        $instructions = file_get_contents($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertIsString($instructions);
        $this->assertStringContainsString('Use package A tools.', $instructions);

        $agents = file_get_contents($this->tempDir.'/AGENTS.md');
        $this->assertIsString($agents);
        $this->assertStringContainsString(AgentInstructionsMaterializer::AGENTS_START_MARKER, $agents);
        $this->assertStringContainsString(AgentInstructionsMaterializer::AGENTS_END_MARKER, $agents);
        $this->assertStringContainsString('AI Mate Summary:', $agents);
        $this->assertStringContainsString('- Role: MCP-powered, project-aware coding guidance and tools.', $agents);
        $this->assertStringContainsString('- Required action: Read and follow `mate/AGENT_INSTRUCTIONS.md` before taking any action in this project, and prefer MCP tools over raw CLI commands whenever possible.', $agents);
        $this->assertStringContainsString('- Installed extensions: vendor/package-a.', $agents);
    }

    public function testSynchronizeFromCurrentInstructionsFileReplacesManagedBlock()
    {
        mkdir($this->tempDir.'/mate', 0755, true);

        file_put_contents(
            $this->tempDir.'/mate/AGENT_INSTRUCTIONS.md',
            "# Updated Instructions\n\nUse synced instructions."
        );

        file_put_contents($this->tempDir.'/AGENTS.md', <<<'MD'
# Project Rules

Keep this line.

<!-- BEGIN AI_MATE_INSTRUCTIONS -->
old content
<!-- END AI_MATE_INSTRUCTIONS -->

Footer line.
MD
        );

        $logger = new NullLogger();
        $aggregator = new AgentInstructionsAggregator($this->tempDir, [], $logger);
        $materializer = new AgentInstructionsMaterializer($this->tempDir, $aggregator, $logger);

        $result = $materializer->synchronizeFromCurrentInstructionsFile();

        $this->assertTrue($result['agents_file_updated']);

        $agents = file_get_contents($this->tempDir.'/AGENTS.md');
        $this->assertIsString($agents);
        $this->assertStringContainsString('Keep this line.', $agents);
        $this->assertStringContainsString('Footer line.', $agents);
        $this->assertStringContainsString('AI Mate Summary:', $agents);
        $this->assertStringContainsString('- Required action: Read and follow `mate/AGENT_INSTRUCTIONS.md` before taking any action in this project, and prefer MCP tools over raw CLI commands whenever possible.', $agents);
        $this->assertStringContainsString('- Installed extensions: See `mate/extensions.php`.', $agents);
        $this->assertSame(1, substr_count($agents, AgentInstructionsMaterializer::AGENTS_START_MARKER));
        $this->assertSame(1, substr_count($agents, AgentInstructionsMaterializer::AGENTS_END_MARKER));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
