<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill\Evaluation\Workspace;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\Evaluation\AssertionResult;
use Symfony\AI\Agent\Skill\Evaluation\BenchmarkResult;
use Symfony\AI\Agent\Skill\Evaluation\BenchmarkStatistic;
use Symfony\AI\Agent\Skill\Evaluation\GradingResult;
use Symfony\AI\Agent\Skill\Evaluation\TimingResult;
use Symfony\AI\Agent\Skill\Evaluation\Workspace\WorkspaceManager;
use Symfony\Component\Filesystem\Filesystem;

final class WorkspaceManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/workspace_test_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testInitializeIteration()
    {
        $manager = new WorkspaceManager($this->tempDir);
        $dir = $manager->initializeIteration(1);

        $this->assertSame($this->tempDir.'/iteration-1', $dir);
        $this->assertDirectoryExists($dir);
    }

    public function testGetEvalDirectory()
    {
        $manager = new WorkspaceManager($this->tempDir);
        $dir = $manager->getEvalDirectory(1, 'test-eval', 'with_skill');

        $this->assertSame($this->tempDir.'/iteration-1/eval-test-eval/with_skill', $dir);
        $this->assertDirectoryExists($dir.'/outputs');
    }

    public function testSaveTimingResult()
    {
        $manager = new WorkspaceManager($this->tempDir);
        $evalDir = $manager->getEvalDirectory(1, 'test', 'with_skill');

        $manager->saveTimingResult($evalDir, new TimingResult(100, 500));

        $content = json_decode(file_get_contents($evalDir.'/timing.json'), true);
        $this->assertSame(100, $content['total_tokens']);
        $this->assertSame(500, $content['duration_ms']);
    }

    public function testSaveGradingResult()
    {
        $manager = new WorkspaceManager($this->tempDir);
        $evalDir = $manager->getEvalDirectory(1, 'test', 'with_skill');

        $grading = new GradingResult([new AssertionResult('test', true, 'evidence')]);
        $manager->saveGradingResult($evalDir, $grading);

        $content = json_decode(file_get_contents($evalDir.'/grading.json'), true);
        $this->assertCount(1, $content['assertions']);
        $this->assertTrue($content['assertions'][0]['passed']);
    }

    public function testSaveBenchmarkResult()
    {
        $manager = new WorkspaceManager($this->tempDir);
        $manager->initializeIteration(1);

        $benchmark = new BenchmarkResult(
            new BenchmarkStatistic(0.8, 0.1),
            new BenchmarkStatistic(0.6, 0.15),
            new BenchmarkStatistic(500.0, 50.0),
            new BenchmarkStatistic(400.0, 40.0),
            new BenchmarkStatistic(150.0, 10.0),
            new BenchmarkStatistic(120.0, 8.0),
        );

        $manager->saveBenchmarkResult(1, $benchmark);

        $content = json_decode(file_get_contents($this->tempDir.'/iteration-1/benchmark.json'), true);
        $this->assertArrayHasKey('with_skill', $content);
        $this->assertArrayHasKey('without_skill', $content);
        $this->assertArrayHasKey('delta', $content);
    }

    public function testSaveOutput()
    {
        $manager = new WorkspaceManager($this->tempDir);
        $evalDir = $manager->getEvalDirectory(1, 'test', 'with_skill');

        $manager->saveOutput($evalDir, 'Agent output text');

        $this->assertSame('Agent output text', file_get_contents($evalDir.'/outputs/output.txt'));
    }
}
