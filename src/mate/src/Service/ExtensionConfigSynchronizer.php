<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Service;

use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Skill\Model\DiscoveredSkill;
use Symfony\AI\Mate\Skill\SkillStateRepository;

/**
 * Reconciles discovered extensions with mate/extensions.php while preserving user intent.
 *
 * This is the discovery half of the state file: it decides which packages keep an entry and seeds
 * newly discovered skills with default intent. It never touches the facts recorded by the installer,
 * and it never drops a skill entry whose source vanished — the installer needs the recorded targets
 * to delete the generated folders before the entry goes away.
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 * @phpstan-import-type SkillState from SkillStateRepository
 * @phpstan-import-type ExtensionConfigMap from SkillStateRepository
 *
 * @phpstan-type SynchronizationResult array{
 *     extensions: ExtensionConfigMap,
 *     new_packages: string[],
 *     removed_packages: string[],
 *     file: string,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExtensionConfigSynchronizer
{
    public function __construct(
        private SkillStateRepository $repository,
    ) {
    }

    public function extensionsFileExists(): bool
    {
        return $this->repository->exists();
    }

    /**
     * @param array<string, ExtensionData> $discoveredExtensions
     * @param list<DiscoveredSkill>        $discoveredSkills
     *
     * @return SynchronizationResult
     */
    public function synchronize(array $discoveredExtensions, array $discoveredSkills = []): array
    {
        $existingExtensions = $this->repository->read();
        $skillsByOwner = $this->groupSkillsByOwner($discoveredSkills);

        $newPackages = [];
        foreach (array_keys($discoveredExtensions) as $packageName) {
            if (!isset($existingExtensions[$packageName])) {
                $newPackages[] = $packageName;
            }
        }

        $removedPackages = [];
        foreach (array_keys($existingExtensions) as $packageName) {
            if ('_custom' === $packageName) {
                continue;
            }

            if (!isset($discoveredExtensions[$packageName])) {
                $removedPackages[] = $packageName;
            }
        }

        // Owners that keep an entry: every discovered package, plus "_custom" when the root ships skills.
        $owners = array_keys($discoveredExtensions);
        if (isset($skillsByOwner['_custom'])) {
            $owners[] = '_custom';
        }

        $finalExtensions = [];
        foreach ($owners as $owner) {
            $enabled = true;
            if (isset($existingExtensions[$owner])) {
                $enabled = $existingExtensions[$owner]['enabled'];
            }

            $config = ['enabled' => $enabled];

            $skills = $this->synchronizeSkills(
                $existingExtensions[$owner]['skills'] ?? [],
                $skillsByOwner[$owner] ?? [],
            );
            if ([] !== $skills) {
                $config['skills'] = $skills;
            }

            $finalExtensions[$owner] = $config;
        }

        $this->repository->write($finalExtensions);

        return [
            'extensions' => $finalExtensions,
            'new_packages' => $newPackages,
            'removed_packages' => $removedPackages,
            'file' => $this->repository->path(),
        ];
    }

    /**
     * @param array<string, SkillState> $existingSkills
     * @param list<string>              $discoveredNames
     *
     * @return array<string, SkillState>
     */
    private function synchronizeSkills(array $existingSkills, array $discoveredNames): array
    {
        $skills = $existingSkills;
        foreach ($discoveredNames as $name) {
            if (isset($skills[$name])) {
                continue;
            }

            $skills[$name] = ['enabled' => true, 'mode' => 'managed'];
        }

        ksort($skills);

        return $skills;
    }

    /**
     * @param list<DiscoveredSkill> $discoveredSkills
     *
     * @return array<string, list<string>>
     */
    private function groupSkillsByOwner(array $discoveredSkills): array
    {
        $grouped = [];
        foreach ($discoveredSkills as $skill) {
            $grouped[$skill->package][] = $skill->originalName;
        }

        return $grouped;
    }
}
