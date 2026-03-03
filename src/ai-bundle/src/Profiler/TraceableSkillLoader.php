<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\Skill\SkillInterface;
use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Skill\SkillMetadataInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @phpstan-type SkillLoaderData array{
 *     skill: string,
 *     skills?: string[],
 *     metadata?: array<string, SkillMetadataInterface>,
 *     loaded_at: \DateTimeImmutable,
 * }
 */
final class TraceableSkillLoader implements SkillLoaderInterface, ResetInterface
{
    /**
     * @var SkillLoaderData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly SkillLoaderInterface $skillLoader,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function loadSkill(string $name): ?SkillInterface
    {
        $this->calls[] = [
            'skill' => $name,
            'loaded_at' => $this->clock->now(),
        ];

        return $this->skillLoader->loadSkill($name);
    }

    public function loadSkills(): array
    {
        $skills = $this->skillLoader->loadSkills();

        $this->calls[] = [
            'skills' => array_keys($skills),
            'loaded_at' => $this->clock->now(),
        ];

        return $skills;
    }

    public function discoverMetadata(): array
    {
        $metadata = $this->skillLoader->discoverMetadata();

        $this->calls[] = [
            'metadata' => $metadata,
            'loaded_at' => $this->clock->now(),
        ];

        return $metadata;
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
