<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command\Trait;

use Symfony\AI\Mate\Exception\AmbiguousSkillException;
use Symfony\AI\Mate\Exception\SkillNotFoundException;
use Symfony\AI\Mate\Skill\SkillManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared name resolution and result rendering for the mutating skills:* commands.
 *
 * Every one of them ends by reinstalling and showing the resulting row, so intent and recorded
 * facts are never left out of sync between two commands.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait RendersSkillOutcomeTrait
{
    /**
     * @return array{package: string, name: string}|null null when the name could not be resolved
     */
    private function resolveSkill(SymfonyStyle $io, SkillManager $manager, string $input): ?array
    {
        try {
            return $manager->resolve($input);
        } catch (SkillNotFoundException|AmbiguousSkillException $exception) {
            $io->error($exception->getMessage());

            return null;
        }
    }

    private function renderSkillRow(SymfonyStyle $io, SkillManager $manager, string $name): void
    {
        $statuses = $manager->statusFor($name);
        if ([] === $statuses) {
            return;
        }

        $table = new Table($io);
        $table->setHeaders(['Installed Name', 'Package', 'Enabled', 'Mode', 'State', 'Status']);

        foreach ($statuses as $status) {
            $table->addRow([
                $status->installedName,
                $status->package,
                $status->enabled ? 'yes' : 'no',
                $status->mode,
                $status->state,
                $status->status,
            ]);
        }

        $table->render();
    }
}
