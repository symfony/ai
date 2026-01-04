<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Profiler\Service;

use Symfony\AI\Mate\Bridge\Profiler\Exception\InvalidCollectorException;
use Symfony\AI\Mate\Bridge\Profiler\Exception\ProfileNotFoundException;
use Symfony\AI\Mate\Bridge\Profiler\Model\ProfileData;
use Symfony\AI\Mate\Bridge\Profiler\Model\ProfileIndex;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Reads and parses profiler data files.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @phpstan-type CollectorDataArray array{
 *     name: string,
 *     data: array<string, mixed>,
 *     summary: array<string, mixed>,
 * }
 *
 * @internal
 */
final class ProfilerDataProvider
{
    /**
     * @var array<string|int, FileProfilerStorage>
     */
    private readonly array $storages;

    /**
     * @param string|array<string, string> $profilerDir Single directory path or array of [context => path]
     */
    public function __construct(
        string|array $profilerDir,
        private readonly CollectorRegistry $collectorRegistry,
        private readonly ProfileIndexer $indexer,
    ) {
        $this->storages = $this->createStorages($profilerDir);
    }

    /**
     * @param string|array<string, string> $profilerDir
     *
     * @return array<string|int, FileProfilerStorage>
     */
    private function createStorages(string|array $profilerDir): array
    {
        if (\is_string($profilerDir)) {
            return [0 => new FileProfilerStorage('file:'.$profilerDir)];
        }

        $storages = [];
        foreach ($profilerDir as $context => $dir) {
            $storages[$context] = new FileProfilerStorage('file:'.$dir);
        }

        return $storages;
    }

    /**
     * @return array<ProfileIndex>
     */
    public function readIndex(?int $limit = null): array
    {
        $allProfiles = [];

        foreach ($this->storages as $context => $storage) {
            // Use find() with minimal filters to get all profiles
            $profiles = $storage->find(null, null, \PHP_INT_MAX, null);

            // Convert to ProfileIndex objects
            foreach ($profiles as $profileData) {
                $allProfiles[] = new ProfileIndex(
                    token: $profileData['token'],
                    ip: $profileData['ip'],
                    method: $profileData['method'],
                    url: $profileData['url'],
                    time: $profileData['time'],
                    statusCode: $profileData['status_code'] ?? null,
                    parentToken: $profileData['parent'] ?? null,
                    context: \is_string($context) ? $context : null,
                    type: $profileData['virtual_type'] ?? null,
                );
            }
        }

        // Sort by time descending (most recent first)
        usort($allProfiles, fn ($a, $b) => $b->time <=> $a->time);

        if (null !== $limit) {
            return \array_slice($allProfiles, 0, $limit);
        }

        return $allProfiles;
    }

    public function findProfile(string $token): ?ProfileData
    {
        foreach ($this->storages as $context => $storage) {
            $profile = $storage->read($token);

            if (null !== $profile) {
                return new ProfileData(
                    profile: $profile,
                    context: \is_string($context) ? $context : null,
                );
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<ProfileIndex>
     */
    public function searchProfiles(array $criteria, int $limit = 20): array
    {
        $allResults = [];

        // Convert time strings to timestamps
        $start = isset($criteria['from']) ? strtotime($criteria['from']) : null;
        $end = isset($criteria['to']) ? strtotime($criteria['to']) : null;

        foreach ($this->storages as $context => $storage) {
            $profiles = $storage->find(
                ip: $criteria['ip'] ?? null,
                url: $criteria['url'] ?? null,
                limit: \PHP_INT_MAX,
                method: $criteria['method'] ?? null,
                start: false !== $start ? $start : null,
                end: false !== $end ? $end : null,
                statusCode: isset($criteria['statusCode']) ? (string) $criteria['statusCode'] : null,
            );

            // Convert to ProfileIndex and filter by context if needed
            foreach ($profiles as $profileData) {
                if (isset($criteria['context']) && $criteria['context'] !== $context) {
                    continue;
                }

                $allResults[] = new ProfileIndex(
                    token: $profileData['token'],
                    ip: $profileData['ip'],
                    method: $profileData['method'],
                    url: $profileData['url'],
                    time: $profileData['time'],
                    statusCode: $profileData['status_code'] ?? null,
                    parentToken: $profileData['parent'] ?? null,
                    context: \is_string($context) ? $context : null,
                    type: $profileData['virtual_type'] ?? null,
                );
            }
        }

        // Sort by time descending
        usort($allResults, fn ($a, $b) => $b->time <=> $a->time);

        return \array_slice($allResults, 0, $limit);
    }

    /**
     * @return CollectorDataArray
     */
    public function getCollectorData(string $token, string $collectorName): array
    {
        $profileData = $this->findProfile($token);

        if (null === $profileData) {
            throw new ProfileNotFoundException(\sprintf('Profile not found for token: "%s"', $token));
        }

        $profile = $profileData->profile;

        if (!$profile->hasCollector($collectorName)) {
            throw new InvalidCollectorException(\sprintf('Collector "%s" not found in profile "%s"', $collectorName, $token));
        }

        $collectorData = $profile->getCollector($collectorName);
        $formatter = $this->collectorRegistry->get($collectorName);

        if (null === $formatter) {
            return [
                'name' => $collectorName,
                'data' => [],
                'summary' => [],
            ];
        }

        return [
            'name' => $collectorName,
            'data' => $formatter->format($collectorData),
            'summary' => $formatter->getSummary($collectorData),
        ];
    }

    /**
     * @return array<string>
     */
    public function listAvailableCollectors(string $token): array
    {
        $profileData = $this->findProfile($token);

        if (null === $profileData) {
            throw new ProfileNotFoundException(\sprintf('Profile not found for token: "%s"', $token));
        }

        return array_keys($profileData->profile->getCollectors());
    }

    public function getLatestProfile(): ?ProfileIndex
    {
        $profiles = $this->readIndex(1);

        return $profiles[0] ?? null;
    }
}
