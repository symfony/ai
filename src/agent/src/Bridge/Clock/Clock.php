<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Clock;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\Component\Clock\Clock as SymfonyClock;
use Symfony\Component\Clock\ClockInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('clock', description: 'Provides the current date and time.')]
final class Clock implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private readonly ClockInterface $clock = new SymfonyClock(),
        private readonly ?string $timezone = null,
    ) {
    }

    public function __invoke(): string
    {
        $now = $this->clock->now();

        if (null !== $this->timezone) {
            $now = $now->setTimezone(new \DateTimeZone($this->timezone));
        }

        $this->addSource(
            new Source('Current Time', 'Clock', $now->format('Y-m-d H:i:s'))
        );

        return \sprintf(
            'Current date is %s (YYYY-MM-DD) and the time is %s (HH:MM:SS).',
            $now->format('Y-m-d'),
            $now->format('H:i:s'),
        );
    }
}
