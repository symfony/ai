<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Skill\SkillInterface;
use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsTool('get_skill', 'Load a skill by name', method: 'loadSkill')]
#[AsTool('get_skills', 'Get all available skills', method: 'loadSkills')]
#[AsTool('execute_skill_script', 'Execute a script from a skill', method: 'executeScript')]
final class SkillTool
{
    public function __construct(
        private readonly SkillLoaderInterface $loader,
        private readonly string $skillName,
    ) {
    }

    /**
     * @param string|null $reference Optional relative path to a reference file within the skill
     */
    public function loadSkill(?string $reference = null): string
    {
        $skill = $this->loader->loadSkill($this->skillName);

        if (!$skill instanceof SkillInterface) {
            return \sprintf('Skill "%s" not found.', $this->skillName);
        }

        $output = \sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody());

        if (null !== $reference) {
            try {
                $referenceContent = $skill->loadReference($reference);
                $output .= \sprintf("\n\n## Reference: %s\n\n%s", $reference, $referenceContent);
            } catch (\RuntimeException $e) {
                $output .= \sprintf("\n\n> Reference \"%s\" could not be loaded: %s", $reference, $e->getMessage());
            }
        }

        return $output;
    }

    public function loadSkills(): array
    {
        $skills = $this->loader->loadSkills();

        if ([] === $skills) {
            return [];
        }

        return array_map(
            static fn (SkillInterface $skill): string => \sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody()),
            $skills,
        );
    }

    /**
     * Execute a script from the skill.
     *
     * @param string $script    The script filename (e.g., 'setup.sh', 'analyze.py')
     * @param array  $arguments Optional command-line arguments to pass to the script
     * @param int    $timeout   Maximum execution time in seconds (default: 60)
     *
     * @return string The script output (stdout and stderr combined)
     */
    public function executeScript(string $script, array $arguments = [], int $timeout = 60): string
    {
        $skill = $this->loader->loadSkill($this->skillName);

        if (!$skill instanceof SkillInterface) {
            return \sprintf('Skill "%s" not found.', $this->skillName);
        }

        try {
            $scriptPath = $skill->loadScript($script);
        } catch (\RuntimeException $e) {
            return \sprintf('Error loading script "%s": %s', $script, $e->getMessage());
        }

        // Determine the interpreter based on file extension
        $interpreter = $this->getInterpreter($scriptPath);
        $command = $interpreter ? [$interpreter, $scriptPath, ...$arguments] : [$scriptPath, ...$arguments];

        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->mustRun();

            return \sprintf("# Script execution: %s\n\n## Output\n\n```\n%s\n```", $script, $process->getOutput());
        } catch (ProcessFailedException $e) {
            return \sprintf(
                "# Script execution failed: %s\n\n## Error\n\n```\n%s\n```\n\n## Output\n\n```\n%s\n```",
                $script,
                $process->getErrorOutput(),
                $process->getOutput()
            );
        }
    }

    /**
     * Determine the interpreter to use based on the script file extension.
     */
    private function getInterpreter(string $scriptPath): ?string
    {
        $extension = pathinfo($scriptPath, \PATHINFO_EXTENSION);

        return match ($extension) {
            'php' => \PHP_BINARY,
            'py' => 'python3',
            'sh' => 'bash',
            'js' => 'node',
            'rb' => 'ruby',
            default => null, // Try to execute directly (must have execute permissions)
        };
    }
}
