<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command;

use HelgeSverre\Toon\Toon;
use Symfony\AI\Mate\Command\Trait\EnsuresToonFormatAvailabilityTrait;
use Symfony\AI\Mate\Skill\Model\SkillStatus;
use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks the installed skills against the state recorded in mate/extensions.php.
 *
 * Read-only. Reports two kinds of finding: errors, where the generated folders no longer match what
 * was recorded (missing, hand-edited, mirror pointing elsewhere, disabled but still present), and
 * warnings, where reality has legitimately moved on (source changed since the last install, a skill
 * declared but never installed, a mirror copied because symlinks are unavailable).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:validate', 'Validate installed Mate skills against the recorded state')]
class SkillsValidateCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;

    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:validate';
    }

    public static function getDefaultDescription(): string
    {
        return 'Validate installed Mate skills against the recorded state';
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Limit validation to one skill (installed "mate-…" or original name)');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json, toon)', 'table');
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Treat warnings as failures');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command compares the generated skill folders against the state
recorded in <comment>mate/extensions.php</comment>.

It exits with <comment>1</comment> when any error is found, and with <comment>--strict</comment> also
when any warning is found. Most findings are fixed by running
<info>mate skills:install</info> or <info>mate skills:prune</info>.
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if (!$this->ensureToonFormatAvailable($io, $format)) {
            return Command::FAILURE;
        }

        $name = $input->getArgument('name');
        \assert(null === $name || \is_string($name));

        $statuses = $this->manager->status();
        if (null !== $name) {
            $statuses = array_values(array_filter(
                $statuses,
                static fn (SkillStatus $status): bool => $status->installedName === $name || $status->originalName === $name,
            ));

            if ([] === $statuses) {
                $io->error(\sprintf('Unknown skill "%s".', $name));

                return Command::FAILURE;
            }
        }

        $strays = null === $name ? $this->manager->pruneStrays(true) : [];

        $hasErrors = false;
        $hasWarnings = [] !== $strays;
        foreach ($statuses as $status) {
            $hasErrors = $hasErrors || $status->hasErrors();
            $hasWarnings = $hasWarnings || $status->hasWarnings();
        }

        $strict = true === $input->getOption('strict');
        $failed = $hasErrors || ($strict && $hasWarnings);

        if ('json' === $format || 'toon' === $format) {
            $result = $this->getArrayResult($statuses, $strays, $failed);
            $output->writeln('json' === $format
                ? json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)
                : Toon::encode($result));

            return $failed ? Command::FAILURE : Command::SUCCESS;
        }

        $this->render($io, $statuses, $strays, $hasErrors, $hasWarnings);

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<SkillStatus> $statuses
     * @param list<string>      $strays
     */
    private function render(SymfonyStyle $io, array $statuses, array $strays, bool $hasErrors, bool $hasWarnings): void
    {
        $io->title('Skill Validation');

        foreach ($statuses as $status) {
            if ([] === $status->issues) {
                continue;
            }

            $io->section($status->installedName);
            foreach ($status->issues as $issue) {
                $io->text(\sprintf(
                    '%s %s',
                    'error' === $issue['level'] ? '<fg=red>ERROR</>' : '<fg=yellow>WARNING</>',
                    $issue['message'],
                ));
            }
        }

        if ([] !== $strays) {
            $io->section('Unexpected folders');
            foreach ($strays as $stray) {
                $io->text(\sprintf('<fg=yellow>WARNING</> "%s" is not owned by any skill; run "mate skills:prune".', $stray));
            }
        }

        if ($hasErrors) {
            $io->error(\sprintf('Validation failed for %d skill(s).', \count(array_filter($statuses, static fn (SkillStatus $s): bool => $s->hasErrors()))));

            return;
        }

        if ($hasWarnings) {
            $io->warning('Validation passed with warnings.');

            return;
        }

        $io->success(\sprintf('All %d skill(s) are valid.', \count($statuses)));
    }

    /**
     * @param list<SkillStatus> $statuses
     * @param list<string>      $strays
     *
     * @return array{
     *     skills: list<array{name: string, status: string, issues: list<array{level: string, message: string}>}>,
     *     strays: list<string>,
     *     valid: bool,
     * }
     */
    private function getArrayResult(array $statuses, array $strays, bool $failed): array
    {
        $skills = [];
        foreach ($statuses as $status) {
            $skills[] = [
                'name' => $status->installedName,
                'status' => $status->status,
                'issues' => $status->issues,
            ];
        }

        return [
            'skills' => $skills,
            'strays' => $strays,
            'valid' => !$failed,
        ];
    }
}
