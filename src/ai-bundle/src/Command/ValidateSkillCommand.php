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

use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Skill\Validation\SkillValidatorInterface;
use Symfony\Component\Console\Attribute\Argument;
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

    public function __invoke(SymfonyStyle $io, #[Argument('The name of the skill to validate')] string $skill): int
    {
        try {
            $skillToValidate = $this->skillLoader->loadSkill($skill);

            $result = $this->skillValidator->validate($skillToValidate);

            $io->table(['Skill', 'Errors', 'Warnings'], [$skillToValidate->getName(), \count($result->getErrors()), \count($result->getWarnings())]);

            if ($result->hasWarnings()) {
                $io->section('Warnings');

                foreach ($result->getWarnings() as $warning) {
                    $io->writeln(\sprintf(' * <fg=yellow>⚠</> %s', $warning));
                }
            }

            if (!$result->isValid()) {
                $io->section('Errors');

                foreach ($result->getErrors() as $error) {
                    $io->writeln(\sprintf(' * <fg=red>✗</> %s', $error));
                }

                return Command::FAILURE;
            }

            $io->success(\sprintf('The skill "%s" is valid.', $skillToValidate->getName()));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('Validation failed for skill "%s": %s', $skill, $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
