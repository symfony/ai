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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read-only diagnostic listing of declared and installed Mate skills.
 *
 * Cross-references the skills declared by extensions, the intent in mate/extensions.php
 * (enabled, mode) and the facts recorded there by the installer (state, hashes, targets). The
 * status column flags skills that are disabled, not yet installed, stale (source moved on) or
 * broken (generated folder missing or modified). Use "skills:validate" for the details.
 *
 * @phpstan-type SkillRow array{
 *     installed_name: string,
 *     original_name: string,
 *     package: string,
 *     enabled: bool,
 *     mode: string,
 *     state: string,
 *     source: string,
 *     status: string,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('skills:list', 'List declared and installed Mate skills with their status')]
class SkillsListCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;

    public function __construct(
        private SkillManager $manager,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'skills:list';
    }

    public static function getDefaultDescription(): string
    {
        return 'List declared and installed Mate skills with their status';
    }

    protected function configure(): void
    {
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json, toon)', 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if (!$this->ensureToonFormatAvailable($io, $format)) {
            return Command::FAILURE;
        }

        $rows = array_map($this->toRow(...), $this->manager->status());

        if ('json' === $format) {
            $output->writeln(json_encode($this->getArrayResult($rows), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ('toon' === $format) {
            $output->writeln(Toon::encode($this->getArrayResult($rows)));

            return Command::SUCCESS;
        }

        $this->outputTable($rows, $io);

        return Command::SUCCESS;
    }

    /**
     * @return SkillRow
     */
    private function toRow(SkillStatus $status): array
    {
        return [
            'installed_name' => $status->installedName,
            'original_name' => $status->originalName,
            'package' => $status->package,
            'enabled' => $status->enabled,
            'mode' => $status->mode,
            'state' => $status->state,
            'source' => $status->source,
            'status' => $status->status,
        ];
    }

    /**
     * @param list<SkillRow> $rows
     */
    private function outputTable(array $rows, SymfonyStyle $io): void
    {
        $io->title('Mate Skills');

        if ([] === $rows) {
            $io->warning('No skills declared by any installed extension or the root project.');

            return;
        }

        $table = new Table($io);
        $table->setHeaders(['Installed Name', 'Original', 'Package', 'Enabled', 'Mode', 'State', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['installed_name'],
                $row['original_name'],
                $row['package'],
                $row['enabled'] ? 'yes' : 'no',
                $row['mode'],
                $row['state'],
                $row['status'],
            ]);
        }

        $table->render();

        $io->newLine();
        $io->text(\sprintf('Total: <info>%d</info> skill(s)', \count($rows)));
    }

    /**
     * @param list<SkillRow> $rows
     *
     * @return array{skills: list<SkillRow>, summary: array{total: int}}
     */
    private function getArrayResult(array $rows): array
    {
        return [
            'skills' => $rows,
            'summary' => [
                'total' => \count($rows),
            ],
        ];
    }
}
