<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Skill\Evaluation\Aggregator\BenchmarkAggregator;
use Symfony\AI\Agent\Skill\Evaluation\EvalSuiteLoader;
use Symfony\AI\Agent\Skill\Evaluation\Workspace\WorkspaceManager;
use Symfony\AI\AiBundle\Command\EvalSkillCommand;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;

final class EvalSkillCommandTest extends TestCase
{
    private string $tempDir;
    private string $workspaceDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/eval_cmd_test_'.bin2hex(random_bytes(4));
        $this->workspaceDir = sys_get_temp_dir().'/eval_workspace_test_'.bin2hex(random_bytes(4));

        $fs = new Filesystem();
        $fs->mkdir($this->tempDir.'/evals');
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->tempDir);
        $fs->remove($this->workspaceDir);
    }

    public function testExecuteWithMissingEvalsFile()
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'skill-directory' => $this->tempDir.'/nonexistent',
            '--agent' => 'test-agent',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Failed to load eval suite', $commandTester->getDisplay());
    }

    public function testExecuteWithMissingAgent()
    {
        $this->createEvalsFile();

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'skill-directory' => $this->tempDir,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('--agent option is required', $commandTester->getDisplay());
    }

    public function testExecuteWithUnknownAgent()
    {
        $this->createEvalsFile();

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'skill-directory' => $this->tempDir,
            '--agent' => 'unknown-agent',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $commandTester->getDisplay());
    }

    public function testExecuteRunsEvalsSuccessfully()
    {
        $this->createEvalsFile();

        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Test response');
        $result->method('getMetadata')->willReturn(new Metadata());

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('call')->willReturn($result);

        $command = $this->createCommand(['test-agent' => $agent]);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'skill-directory' => $this->tempDir,
            '--agent' => 'test-agent',
            '--skip-grading' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Evaluation complete', $commandTester->getDisplay());
    }

    /**
     * @param array<string, AgentInterface> $agents
     */
    private function createCommand(array $agents = []): EvalSkillCommand
    {
        $locatorMap = [];
        foreach ($agents as $name => $agent) {
            $locatorMap[$name] = static fn (): AgentInterface => $agent;
        }

        return new EvalSkillCommand(
            new EvalSuiteLoader(),
            new WorkspaceManager($this->workspaceDir),
            new BenchmarkAggregator(),
            new MockClock('2026-01-01 10:00:00'),
            new ServiceLocator($locatorMap),
        );
    }

    private function createEvalsFile(): void
    {
        $data = [
            'skill_name' => 'test-skill',
            'evals' => [
                ['id' => 1, 'prompt' => 'What is PHP?', 'expected_output' => 'A language'],
            ],
        ];

        (new Filesystem())->dumpFile($this->tempDir.'/evals/evals.json', json_encode($data));
    }
}
