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

use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

trait EnsuresToonFormatAvailabilityTrait
{
    /**
     * @internal Used in tests to mock TOON availability
     */
    protected function isToonFormatAvailable(): bool
    {
        return ContainerBuilder::willBeAvailable('helgesverre/toon', Toon::class, ['symfony/ai-mate']);
    }

    private function ensureToonFormatAvailable(SymfonyStyle $io, string $format): bool
    {
        if ('toon' !== $format) {
            return true;
        }

        if ($this->isToonFormatAvailable()) {
            return true;
        }

        $io->error('The "toon" output format requires the `helgesverre/toon` package.');
        $io->note('Install it with: `composer require helgesverre/toon`');

        return false;
    }
}
