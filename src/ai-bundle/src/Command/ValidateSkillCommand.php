<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Command;

use Symfony\AI\Agent\Skill\SkillInterface;
use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Skill\Validation\SkillValidationResult;
use Symfony\AI\Agent\Skill\Validation\SkillValidatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'ai:agent:validate-skills',
    description: 'Validate Agent Skills against the specification',
)]
final class ValidateSkillCommand
{
    public function __construct(
        private readonly SkillLoaderInterface $skillLoader,
        private readonly SkillValidatorInterface $skillValidator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $skills = $this->skillLoader->loadSkills();

            if ([] === $skills) {
                $io->warning('No skills found.');

                return Command::SUCCESS;
            }

            $validationResult = array_map(
                fn (SkillInterface $skill): SkillValidationResult => $this->skillValidator->validate($skill),
                $skills,
            );

            $validCount = 0;
            $invalidCount = 0;
            $warningCount = 0;

            foreach ($validationResult as $result) {
                $this->displayResult($io, $result);

                if ($result->isValid()) {
                    ++$validCount;
                } else {
                    ++$invalidCount;
                }

                if ($result->hasWarnings()) {
                    ++$warningCount;
                }
            }

            $io->newLine();
            $io->section('Summary');

            $summaryData = [
                ['Total', \count($skills)],
                ['Valid', \sprintf('<fg=green>%d</>', $validCount)],
                ['Invalid', $invalidCount > 0 ? \sprintf('<fg=red>%d</>', $invalidCount) : '0'],
                ['With warnings', $warningCount > 0 ? \sprintf('<fg=yellow>%d</>', $warningCount) : '0'],
            ];

            $io->table(['Metric', 'Count'], $summaryData);

            if ($invalidCount > 0) {
                $io->error(\sprintf('%d skill(s) failed validation. See errors above.', $invalidCount));

                return Command::FAILURE;
            }

            if ($warningCount > 0) {
                $io->success(\sprintf('All skills are valid! (%d warning(s) found)', $warningCount));
            } else {
                $io->success('All skills are valid!');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('Validation failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function displayResult(SymfonyStyle $io, SkillValidationResult $result): void
    {
        $skillName = $result->getSkillName();

        if ($result->isValid()) {
            $status = '<fg=green>✓</> valid';
        } else {
            $status = \sprintf('<fg=red>✗</> <fg=red>%d error(s)</>', \count($result->getErrors()));
        }

        if ($result->hasWarnings()) {
            $status .= \sprintf(' <fg=yellow>⚠ %d warning(s)</>', \count($result->getWarnings()));
        }

        $io->writeln(\sprintf('<info>%s:</info> %s', $skillName, $status));

        // Display errors
        foreach ($result->getErrors() as $error) {
            $io->writeln(\sprintf('  <fg=red>✗</> %s', $error));
        }

        // Display warnings only in verbose mode or if there are warnings
        if ($io->isVerbose() || $result->hasWarnings()) {
            foreach ($result->getWarnings() as $warning) {
                $io->writeln(\sprintf('  <fg=yellow>⚠</> %s', $warning));
            }
        }

        $io->newLine();
    }
}
