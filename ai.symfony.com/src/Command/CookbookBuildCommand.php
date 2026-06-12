<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Cookbook\Builder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsCommand(
    name: 'app:cookbook:build',
    description: 'Generate the cookbook article fragments from the RST documentation',
)]
final readonly class CookbookBuildCommand
{
    public function __construct(
        private Builder $builder,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Build cookbook article fragments');

        $slugs = $this->builder->generate();

        $io->success(\sprintf('Generated %d cookbook article(s):', \count($slugs)));
        $io->listing($slugs);

        return Command::SUCCESS;
    }
}
