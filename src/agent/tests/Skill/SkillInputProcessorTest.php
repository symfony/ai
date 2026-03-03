<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\SkillInputProcessor;
use Symfony\AI\Agent\Skill\FilesystemSkillLoader;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Filesystem\Filesystem;

final class SkillInputProcessorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/skill_processor_test_'.bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testProcessInputDoesNothingWhenNoSkills()
    {
        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $processor = new SkillInputProcessor($discovery);

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('Hello')));
        $processor->processInput($input);

        $this->assertArrayNotHasKey('system_prompt', $input->getOptions());
    }

    public function testProcessInputIncludesSkillIndex()
    {
        $this->createSkillDirectory('code-review', 'Reviews code changes');
        $this->createSkillDirectory('pdf-reader', 'Reads PDF documents');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $processor = new SkillInputProcessor($discovery, includeIndex: true);

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('Hello')));
        $processor->processInput($input);

        $options = $input->getOptions();
        $this->assertArrayHasKey('system_prompt', $options);
        $this->assertStringContainsString('# Agent Skills', $options['system_prompt']);
        $this->assertStringContainsString('## Available Skills', $options['system_prompt']);
        $this->assertStringContainsString('code-review', $options['system_prompt']);
        $this->assertStringContainsString('pdf-reader', $options['system_prompt']);
    }

    public function testProcessInputDoesNotIncludeIndexWhenDisabled()
    {
        $this->createSkillDirectory('my-skill', 'A skill');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $processor = new SkillInputProcessor($discovery, activeSkills: [], includeIndex: false);

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('Hello')));
        $processor->processInput($input);

        $this->assertArrayNotHasKey('system_prompt', $input->getOptions());
    }

    public function testProcessInputLoadsActiveSkillsFully()
    {
        $this->createSkillDirectory('code-review', 'Reviews code');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $processor = new SkillInputProcessor($discovery, activeSkills: ['code-review'], includeIndex: false);

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('Hello')));
        $processor->processInput($input);

        $options = $input->getOptions();
        $this->assertArrayHasKey('system_prompt', $options);
        $this->assertStringContainsString('## Skill: code-review', $options['system_prompt']);
        $this->assertStringContainsString('Instructions for code-review.', $options['system_prompt']);
    }

    public function testProcessInputIgnoresMissingActiveSkills()
    {
        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $processor = new SkillInputProcessor($discovery, activeSkills: ['non-existent'], includeIndex: false);

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('Hello')));
        $processor->processInput($input);

        $this->assertArrayNotHasKey('system_prompt', $input->getOptions());
    }

    public function testProcessInputCombinesIndexAndActiveSkills()
    {
        $this->createSkillDirectory('skill-a', 'Skill A description');
        $this->createSkillDirectory('skill-b', 'Skill B description');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $processor = new SkillInputProcessor($discovery, activeSkills: ['skill-a'], includeIndex: true);

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('Hello')));
        $processor->processInput($input);

        $options = $input->getOptions();
        $prompt = $options['system_prompt'];

        // Index should contain both skills
        $this->assertStringContainsString('## Available Skills', $prompt);
        $this->assertStringContainsString('skill-a', $prompt);
        $this->assertStringContainsString('skill-b', $prompt);

        // Active skill body should be loaded
        $this->assertStringContainsString('## Skill: skill-a', $prompt);
        $this->assertStringContainsString('Instructions for skill-a.', $prompt);
    }

    public function testProcessInputAppendsToExistingSystemPrompt()
    {
        $this->createSkillDirectory('my-skill', 'A skill');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $processor = new SkillInputProcessor($discovery, includeIndex: true);

        $input = new Input('gpt-4o', new MessageBag(Message::ofUser('Hello')), [
            'system_prompt' => 'You are a helpful assistant.',
        ]);
        $processor->processInput($input);

        $options = $input->getOptions();
        $this->assertStringStartsWith('You are a helpful assistant.', $options['system_prompt']);
        $this->assertStringContainsString('# Agent Skills', $options['system_prompt']);
    }

    private function createSkillDirectory(string $name, string $description): void
    {
        $skillDir = $this->tempDir.'/'.$name;

        (new Filesystem())->mkdir($skillDir);
        (new Filesystem())->dumpFile(
            $skillDir.'/SKILL.md',
            \sprintf("---\nname: %s\ndescription: %s\n---\nInstructions for %s.", $name, $description, $name),
        );
    }
}
