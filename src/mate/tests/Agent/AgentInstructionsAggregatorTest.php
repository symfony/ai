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

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AgentInstructionsAggregatorTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-aggregator-'.uniqid();
        mkdir($this->rootDir.'/vendor/acme/ext', 0777, true);
        file_put_contents($this->rootDir.'/vendor/acme/ext/INSTRUCTIONS.md', '# Acme Instructions');
        file_put_contents($this->rootDir.'/secret.md', '# Secret');
    }

    protected function tearDown(): void
    {
        $files = [
            $this->rootDir.'/vendor/acme/ext/INSTRUCTIONS.md',
            $this->rootDir.'/secret.md',
        ];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach ([$this->rootDir.'/vendor/acme/ext', $this->rootDir.'/vendor/acme', $this->rootDir.'/vendor', $this->rootDir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testAggregatesExtensionInstructions()
    {
        $aggregator = new AgentInstructionsAggregator(
            $this->rootDir,
            ['acme/ext' => ['dirs' => [], 'includes' => [], 'instructions' => 'INSTRUCTIONS.md', 'skills' => []]],
            new NullLogger(),
        );

        $result = $aggregator->aggregate();

        $this->assertNotNull($result);
        $this->assertStringContainsString('Acme Instructions', $result);
    }

    public function testRejectsTraversingExtensionInstructions()
    {
        $aggregator = new AgentInstructionsAggregator(
            $this->rootDir,
            ['acme/ext' => ['dirs' => [], 'includes' => [], 'instructions' => '../../../secret.md', 'skills' => []]],
            new NullLogger(),
        );

        $this->assertNull($aggregator->aggregate());
    }

    public function testRejectsTraversingRootProjectInstructions()
    {
        $aggregator = new AgentInstructionsAggregator(
            $this->rootDir,
            ['_custom' => ['dirs' => [], 'includes' => [], 'instructions' => '../secret.md', 'skills' => []]],
            new NullLogger(),
        );

        $this->assertNull($aggregator->aggregate());
    }
}
