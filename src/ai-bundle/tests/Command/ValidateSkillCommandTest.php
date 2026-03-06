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
use Symfony\AI\Agent\Skill\Skill;
use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Skill\SkillMetadata;
use Symfony\AI\Agent\Skill\Validation\SkillValidator;
use Symfony\AI\AiBundle\Command\ValidateSkillCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ValidateSkillCommandTest extends TestCase
{
    public function testExecuteWithAllValidSkills()
    {
        $metadata1 = new SkillMetadata('skill-one', 'First skill with proper description');
        $skill1 = new Skill('Body content 1.', $metadata1);

        $metadata2 = new SkillMetadata('skill-two', 'Second skill with proper description');
        $skill2 = new Skill('Body content 2.', $metadata2);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkills')
            ->willReturn([
                'skill-one' => $skill1,
                'skill-two' => $skill2,
            ]);

        $validator = new SkillValidator();
        $command = new ValidateSkillCommand($loader, $validator);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('skill-one', $commandTester->getDisplay());
        $this->assertStringContainsString('skill-two', $commandTester->getDisplay());
        $this->assertStringContainsString('valid', $commandTester->getDisplay());
        $this->assertStringContainsString('All skills are valid!', $commandTester->getDisplay());
    }

    public function testExecuteWithInvalidSkill()
    {
        $metadata1 = new SkillMetadata('valid-skill', 'A properly described skill for testing');
        $skill1 = new Skill('Body content.', $metadata1);

        $metadata2 = new SkillMetadata('Invalid_Skill', 'Another skill with invalid name');
        $skill2 = new Skill('Body content.', $metadata2);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkills')
            ->willReturn([
                'valid-skill' => $skill1,
                'Invalid_Skill' => $skill2,
            ]);

        $validator = new SkillValidator();
        $command = new ValidateSkillCommand($loader, $validator);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid_Skill', $commandTester->getDisplay());
        $this->assertStringContainsString('error', $commandTester->getDisplay());
        $this->assertStringContainsString('kebab-case', $commandTester->getDisplay());
    }

    public function testExecuteWithSpecificValidSkill()
    {
        $metadata = new SkillMetadata('test-skill', 'A test skill with proper description');
        $skill = new Skill('Instructions here.', $metadata);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkill')
            ->with('test-skill')
            ->willReturn($skill);

        $validator = new SkillValidator();
        $command = new ValidateSkillCommand($loader, $validator);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--skill' => 'test-skill']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('test-skill', $commandTester->getDisplay());
        $this->assertStringContainsString('valid', $commandTester->getDisplay());
    }

    public function testExecuteWithNonExistentSkill()
    {
        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkill')
            ->with('missing-skill')
            ->willReturn(null);

        $validator = new SkillValidator();
        $command = new ValidateSkillCommand($loader, $validator);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['skill' => 'missing-skill']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('missing-skill', $commandTester->getDisplay());
        $this->assertStringContainsString('not found', $commandTester->getDisplay());
    }

    public function testExecuteWithWarnings()
    {
        $metadata = new SkillMetadata('my-skill', 'Short desc');
        $skill = new Skill('', $metadata);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkills')
            ->willReturn(['my-skill' => $skill]);

        $validator = new SkillValidator();
        $command = new ValidateSkillCommand($loader, $validator);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('my-skill', $commandTester->getDisplay());
        $this->assertStringContainsString('warning', $commandTester->getDisplay());
    }

    public function testDisplaysSummary()
    {
        $metadata1 = new SkillMetadata('skill-one', 'First skill with proper description');
        $skill1 = new Skill('Body 1.', $metadata1);

        $metadata2 = new SkillMetadata('Invalid_Name', 'Second skill with invalid name');
        $skill2 = new Skill('Body 2.', $metadata2);

        $loader = $this->createMock(SkillLoaderInterface::class);
        $loader->method('loadSkills')
            ->willReturn([
                'skill-one' => $skill1,
                'Invalid_Name' => $skill2,
            ]);

        $validator = new SkillValidator();
        $command = new ValidateSkillCommand($loader, $validator);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);
        $display = $commandTester->getDisplay();

        $this->assertStringContainsString('Summary', $display);
        $this->assertStringContainsString('Total', $display);
        $this->assertStringContainsString('Valid', $display);
        $this->assertStringContainsString('Invalid', $display);
    }
}
