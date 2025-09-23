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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\Clock\ClockInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('clock', description: 'Provides the current date and time.')]
final readonly class Clock
{
    public function __construct(
        private ClockInterface $clock = new SymfonyClock(),
        private ?string $timezone = null,
    ) {
    }

    public function __invoke(): string
    {
        $now = $this->clock->now();

        if (null !== $this->timezone) {
            $now = $now->setTimezone(new \DateTimeZone($this->timezone));
        }

        return \sprintf(
            'Current date is %s (YYYY-MM-DD) and the time is %s (HH:MM:SS).',
            $now->format('Y-m-d'),
            $now->format('H:i:s'),
        );
    }
}
