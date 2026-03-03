<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Tool;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\Skill;
use Symfony\AI\Agent\Skill\SkillInterface;
use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Skill\SkillMetadata;
use Symfony\AI\Agent\Toolbox\Tool\SkillTool;

final class SkillToolTest extends TestCase
{
    public function testInvokeReturnsSkillBody()
    {
        $metadata = new SkillMetadata('my-skill', 'A test skill description');
        $skill = new Skill('# Instructions\n\nDo the thing.', $metadata);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->expects($this->once())->method('loadSkill')
            ->with('my-skill')
            ->willReturn($skill);

        $tool = new SkillTool($loader, 'my-skill');
        $result = $tool->loadSkill();

        $this->assertStringContainsString('# Skill: my-skill', $result);
        $this->assertStringContainsString('# Instructions', $result);
    }

    public function testInvokeReturnsNotFoundMessage()
    {
        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->expects($this->once())->method('loadSkill')
            ->with('unknown-skill')
            ->willReturn(null);

        $tool = new SkillTool($loader, 'unknown-skill');
        $result = $tool->loadSkill();

        $this->assertSame('Skill "unknown-skill" not found.', $result);
    }

    public function testInvokeWithReferenceAppendsContent()
    {
        $metadata = new SkillMetadata('my-skill', 'A test skill description');
        $skill = $this->createMock(SkillInterface::class);
        $skill->expects($this->once())->method('getName')->willReturn('my-skill');
        $skill->expects($this->once())->method('getBody')->willReturn('Body content');
        $skill->expects($this->once())->method('loadReference')
            ->with('api.md')
            ->willReturn('## API Reference content');

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkill')
            ->with('my-skill')
            ->willReturn($skill);

        $tool = new SkillTool($loader, 'my-skill');
        $result = $tool->loadSkill('api.md');

        $this->assertStringContainsString('## Reference: api.md', $result);
        $this->assertStringContainsString('## API Reference content', $result);
    }

    public function testExecuteScriptSuccessfully()
    {
        $scriptPath = sys_get_temp_dir().'/test_script.sh';
        file_put_contents($scriptPath, "#!/bin/bash\necho 'Hello from script'");
        chmod($scriptPath, 0755);

        $skill = $this->createMock(SkillInterface::class);
        $skill->expects($this->once())->method('loadScript')
            ->with('test.sh')
            ->willReturn($scriptPath);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkill')
            ->with('my-skill')
            ->willReturn($skill);

        $tool = new SkillTool($loader, 'my-skill');
        $result = $tool->executeScript('test.sh');

        $this->assertStringContainsString('# Script execution: test.sh', $result);
        $this->assertStringContainsString('Hello from script', $result);

        unlink($scriptPath);
    }

    public function testExecuteScriptWithArguments()
    {
        $scriptPath = sys_get_temp_dir().'/test_script_args.php';
        file_put_contents($scriptPath, "<?php echo 'Args: ' . implode(', ', array_slice(\$argv, 1));");

        $skill = $this->createMock(SkillInterface::class);
        $skill->expects($this->once())->method('loadScript')
            ->with('test.php')
            ->willReturn($scriptPath);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkill')
            ->with('my-skill')
            ->willReturn($skill);

        $tool = new SkillTool($loader, 'my-skill');
        $result = $tool->executeScript('test.php', ['arg1', 'arg2']);

        $this->assertStringContainsString('Args: arg1, arg2', $result);

        unlink($scriptPath);
    }

    public function testExecuteScriptNotFound()
    {
        $skill = $this->createMock(SkillInterface::class);
        $skill->expects($this->once())->method('loadScript')
            ->with('missing.sh')
            ->willThrowException(new \RuntimeException('Script "missing.sh" not found in skill scripts directory.'));

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkill')
            ->with('my-skill')
            ->willReturn($skill);

        $tool = new SkillTool($loader, 'my-skill');
        $result = $tool->executeScript('missing.sh');

        $this->assertStringContainsString('Error loading script', $result);
        $this->assertStringContainsString('missing.sh', $result);
    }

    public function testExecuteScriptFailure()
    {
        $scriptPath = sys_get_temp_dir().'/test_script_fail.sh';
        file_put_contents($scriptPath, "#!/bin/bash\necho 'Error message' >&2\nexit 1");
        chmod($scriptPath, 0755);

        $skill = $this->createMock(SkillInterface::class);
        $skill->method('loadScript')
            ->with('fail.sh')
            ->willReturn($scriptPath);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkill')
            ->with('my-skill')
            ->willReturn($skill);

        $tool = new SkillTool($loader, 'my-skill');
        $result = $tool->executeScript('fail.sh');

        $this->assertStringContainsString('Script execution failed', $result);
        $this->assertStringContainsString('Error message', $result);

        unlink($scriptPath);
    }
}
