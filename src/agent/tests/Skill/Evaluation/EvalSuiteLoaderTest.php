<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill\Evaluation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Skill\Evaluation\EvalSuiteLoader;
use Symfony\Component\Filesystem\Filesystem;

final class EvalSuiteLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/eval_suite_loader_test_'.bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir.'/evals');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testLoadValidJson()
    {
        $data = [
            'skill_name' => 'test-skill',
            'evals' => [
                ['id' => 1, 'prompt' => 'What is PHP?', 'expected_output' => 'A language'],
                ['id' => 2, 'prompt' => 'Explain OOP', 'expected_output' => 'Object-oriented programming', 'files' => ['src/Foo.php'], 'assertions' => ['Mentions classes']],
            ],
        ];

        (new Filesystem())->dumpFile($this->tempDir.'/evals/evals.json', json_encode($data));

        $suite = (new EvalSuiteLoader())->load($this->tempDir);

        $this->assertSame('test-skill', $suite->getSkillName());
        $this->assertCount(2, $suite->getEvals());

        $first = $suite->getEvalById(1);
        $this->assertNotNull($first);
        $this->assertSame('What is PHP?', $first->getPrompt());
        $this->assertSame([], $first->getFiles());

        $second = $suite->getEvalById(2);
        $this->assertNotNull($second);
        $this->assertSame(['src/Foo.php'], $second->getFiles());
        $this->assertSame(['Mentions classes'], $second->getAssertions());
    }

    public function testLoadThrowsWhenFileMissing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Eval file not found');

        (new EvalSuiteLoader())->load($this->tempDir.'/nonexistent');
    }

    public function testLoadThrowsOnMalformedJson()
    {
        (new Filesystem())->dumpFile($this->tempDir.'/evals/evals.json', '{invalid json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse JSON');

        (new EvalSuiteLoader())->load($this->tempDir);
    }

    public function testLoadThrowsOnMissingSkillName()
    {
        $data = ['evals' => [['id' => 1, 'prompt' => 'test', 'expected_output' => 'out']]];

        (new Filesystem())->dumpFile($this->tempDir.'/evals/evals.json', json_encode($data));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "skill_name"');

        (new EvalSuiteLoader())->load($this->tempDir);
    }

    public function testLoadThrowsOnMissingEvals()
    {
        $data = ['skill_name' => 'test-skill'];

        (new Filesystem())->dumpFile($this->tempDir.'/evals/evals.json', json_encode($data));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "evals"');

        (new EvalSuiteLoader())->load($this->tempDir);
    }

    public function testLoadThrowsOnMissingRequiredEvalFields()
    {
        $data = [
            'skill_name' => 'test-skill',
            'evals' => [['id' => 1, 'prompt' => 'test']],
        ];

        (new Filesystem())->dumpFile($this->tempDir.'/evals/evals.json', json_encode($data));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "expected_output"');

        (new EvalSuiteLoader())->load($this->tempDir);
    }
}
