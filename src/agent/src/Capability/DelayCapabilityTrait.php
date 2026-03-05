<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Capability;

use Symfony\Component\Clock\Clock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait DelayCapabilityTrait
{
    /**
     * @param int $delay The delay in milliseconds
     */
    public function __construct(
        private readonly int $delay,
    ) {
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public static function delayFor(\DateInterval $interval): static
    {
        $now = Clock::get()->withTimeZone(new \DateTimeZone('UTC'))->now();
        $end = $now->add($interval);

        return new static(($end->getTimestamp() - $now->getTimestamp()) * 1000);
    }

    public static function delayUntil(\DateTimeInterface $dateTime): static
    {
        return new static(($dateTime->getTimestamp() - Clock::get()->now()->getTimestamp()) * 1000);
    }
}
