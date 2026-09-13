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

trait EnsuresToonFormatAvailabilityTrait
{
    /**
     * @internal Used to check TOON availability
     */
    protected function isToonFormatAvailable(): bool
    {
        return class_exists(Toon::class);
    }

    /**
     * Rejects a format the command cannot produce, then checks that TOON is installed.
     *
     * Falling back to the default for an unknown value answers a request for machine-readable
     * output with a human table and a success exit code, which the caller cannot detect.
     *
     * @param list<string> $supported
     */
    private function ensureFormatSupported(SymfonyStyle $io, string $format, array $supported): bool
    {
        if (!\in_array($format, $supported, true)) {
            $io->error(\sprintf('Unknown output format "%s". Supported: "%s".', $format, implode('", "', $supported)));

            return false;
        }

        return $this->ensureToonFormatAvailable($io, $format);
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
