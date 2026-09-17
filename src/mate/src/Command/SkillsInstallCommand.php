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
use Symfony\AI\Mate\Skill\Model\SkillInstallResult;
use Symfony\AI\Mate\Skill\Model\SkillStatus;
use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reconcile the generated skill folders from source + intent.
 *
 * This is the one idempotent reconciler: it rebuilds .agents/skills/ and .claude/skills/ from the
 * vendor sources (or user overrides in mate/skills/), prunes skills of disabled or removed
 * extensions, and records what it did back into mate/extensions.php. It never writes into
 * mate/skills/ (user-owned overrides).
 *
 * @phpstan-type SkillRow array{
 *     installed_name: string,
 *     original_name: string,
 *     package: string,
 *     enabled: bool,
 *     mode: string,
 *     state: string,
 *     status: string,
 *     action: string,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:install', 'Install and reconcile Mate skills into the generated agent folders')]
class SkillsInstallCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;

    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:install';
    }

    public static function getDefaultDescription(): string
    {
        return 'Install and reconcile Mate skills into the generated agent folders';
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json, toon)', 'table');
        $this->setHelp(
            <<<'HELP'
The <info>%command.name%</info> command rebuilds the generated skill folders
(<comment>.agents/skills/</comment> and <comment>.claude/skills/</comment>) from the skills declared
by installed Mate extensions and the root project.

Both the intent it reads (<comment>enabled</comment>, <comment>mode</comment>) and the facts it
records (<comment>state</comment>, <comment>source</comment>, hashes, targets) live in
<comment>mate/extensions.php</comment>. The command is idempotent: running it repeatedly converges on
the same result and never touches your overrides in <comment>mate/skills/</comment>.

Pass <comment>--dry-run</comment> to see what a run would change. The same reconciler runs, it just
writes nothing: no generated folder is touched and <comment>mate/extensions.php</comment> stays as it
is.
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if (!$this->ensureFormatSupported($io, $format, ['table', 'json', 'toon'])) {
            return Command::FAILURE;
        }

        $result = $this->manager->reinstall($dryRun);

        if ('table' === $format) {
            $this->render($io, $result, $dryRun);

            return Command::SUCCESS;
        }

        $data = $this->getArrayResult($result, $dryRun);

        if ('json' === $format) {
            $output->writeln(json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $output->writeln(Toon::encode($data));

        return Command::SUCCESS;
    }

    private function render(SymfonyStyle $io, SkillInstallResult $result, bool $dryRun): void
    {
        $io->title($dryRun ? 'Skill Installation (dry run)' : 'Skill Installation');

        if ([] !== $result->installed) {
            $message = \sprintf('%s %d new skill%s: %s', $dryRun ? 'Would install' : 'Installed', \count($result->installed), 1 === \count($result->installed) ? '' : 's', implode(', ', $result->installed));
            if ($dryRun) {
                $io->text($message);
            } else {
                $io->success($message);
            }
        }

        if ([] !== $result->updated) {
            $io->text(\sprintf('%s %d skill%s: %s', $dryRun ? 'Would rebuild' : 'Rebuilt', \count($result->updated), 1 === \count($result->updated) ? '' : 's', implode(', ', $result->updated)));
        }

        if ([] !== $result->removed) {
            $io->text(\sprintf('%s %d skill%s: %s', $dryRun ? 'Would remove' : 'Removed', \count($result->removed), 1 === \count($result->removed) ? '' : 's', implode(', ', $result->removed)));
        }

        foreach ($result->skipped as $name => $reason) {
            $io->warning(\sprintf('%s %s: %s', $dryRun ? 'Would skip' : 'Skipped', $name, $reason));
        }

        foreach ($result->notices as $notice) {
            $io->note($notice);
        }

        $rows = $this->buildRows($result, $dryRun);

        if ([] === $result->active) {
            $io->text($dryRun ? 'No skills would be installed.' : 'No skills are currently installed.');

            if ([] !== $rows) {
                $io->newLine();
                $this->outputTable($rows, $io);
            }

            return;
        }

        $io->text(\sprintf('%d skill%s %s: %s', \count($result->active), 1 === \count($result->active) ? '' : 's', $dryRun ? 'would be installed' : 'installed', implode(', ', $result->active)));

        if ([] !== $rows) {
            $io->newLine();
            $this->outputTable($rows, $io);
        }

        if ($dryRun && [] === $result->installed && [] === $result->updated && [] === $result->removed) {
            $io->success('Nothing to do, the generated folders are up to date.');
        }
    }

    /**
     * @return list<SkillRow>
     */
    private function buildRows(SkillInstallResult $result, bool $dryRun): array
    {
        $names = array_unique(array_merge($result->active, array_keys($result->skipped)));
        sort($names);

        if ([] === $names) {
            return [];
        }

        $statuses = [];
        foreach ($this->manager->status() as $status) {
            $statuses[$status->installedName] = $status;
        }

        $rows = [];
        foreach ($names as $name) {
            $status = $statuses[$name] ?? null;
            if (null === $status) {
                continue;
            }

            $rows[] = $this->toRow($status, $this->resolveAction($result, $name, $dryRun));
        }

        return $rows;
    }

    private function resolveAction(SkillInstallResult $result, string $name, bool $dryRun): string
    {
        if (\in_array($name, $result->installed, true)) {
            return $dryRun ? 'would install' : 'installed';
        }

        if (\in_array($name, $result->updated, true)) {
            return $dryRun ? 'would rebuild' : 'rebuilt';
        }

        if (isset($result->skipped[$name])) {
            return $dryRun ? 'would skip' : 'skipped';
        }

        return 'unchanged';
    }

    /**
     * @return SkillRow
     */
    private function toRow(SkillStatus $status, string $action): array
    {
        return [
            'installed_name' => $status->installedName,
            'original_name' => $status->originalName,
            'package' => $status->package,
            'enabled' => $status->enabled,
            'mode' => $status->mode,
            'state' => $status->state,
            'status' => $status->status,
            'action' => $action,
        ];
    }

    /**
     * @param list<SkillRow> $rows
     */
    private function outputTable(array $rows, SymfonyStyle $io): void
    {
        $table = new Table($io);
        $table->setHeaders(['Installed Name', 'Original', 'Package', 'Enabled', 'Mode', 'State', 'Status', 'Action']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['installed_name'],
                $row['original_name'],
                $row['package'],
                $row['enabled'] ? 'yes' : 'no',
                $row['mode'],
                $row['state'],
                $row['status'],
                $row['action'],
            ]);
        }

        $table->render();
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     installed: list<string>,
     *     updated: list<string>,
     *     removed: list<string>,
     *     skipped: array<string, string>,
     *     notices: list<string>,
     *     skills: list<SkillRow>,
     *     summary: array{total: int, installed: int, updated: int, removed: int, skipped: int},
     * }
     */
    private function getArrayResult(SkillInstallResult $result, bool $dryRun): array
    {
        return [
            'dry_run' => $dryRun,
            'installed' => $result->installed,
            'updated' => $result->updated,
            'removed' => $result->removed,
            'skipped' => $result->skipped,
            'notices' => $result->notices,
            'skills' => $this->buildRows($result, $dryRun),
            'summary' => [
                'total' => \count($result->active),
                'installed' => \count($result->installed),
                'updated' => \count($result->updated),
                'removed' => \count($result->removed),
                'skipped' => \count($result->skipped),
            ],
        ];
    }
}
