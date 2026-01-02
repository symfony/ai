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
     * @param string|array<string, string> $profilerDir Single directory path or array of [context => path]
     */
    public function __construct(
        private readonly string|array $profilerDir,
        private readonly CollectorRegistry $collectorRegistry,
        private readonly ProfileIndexer $indexer,
    ) {
    }

    /**
     * @return array<ProfileIndex>
     */
    public function readIndex(?int $limit = null): array
    {
        $profiles = $this->indexer->indexProfiles($this->profilerDir);

        if (null !== $limit) {
            return \array_slice($profiles, 0, $limit);
        }

        return $profiles;
    }

    public function findProfile(string $token): ?ProfileData
    {
        foreach ($this->getDirectories() as $context => $dir) {
            // Symfony stores profiles in nested directories: {last_2_chars}/{next_to_last_2_chars}/{token}
            $dir1 = substr($token, -2);
            $dir2 = substr($token, -4, 2);
            $profileFile = $dir.'/'.$dir1.'/'.$dir2.'/'.$token;

            if (file_exists($profileFile) && is_readable($profileFile)) {
                return $this->readProfileFile($profileFile, $token, $context);
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
        $profiles = $this->indexer->indexProfiles($this->profilerDir);

        if (isset($criteria['context'])) {
            $profiles = $this->indexer->filterByContext($profiles, $criteria['context']);
        }

        if (isset($criteria['method'])) {
            $profiles = $this->indexer->filterByMethod($profiles, $criteria['method']);
        }

        if (isset($criteria['statusCode'])) {
            $profiles = $this->indexer->filterByStatusCode($profiles, (int) $criteria['statusCode']);
        }

        if (isset($criteria['url'])) {
            $profiles = $this->indexer->filterByUrl($profiles, $criteria['url']);
        }

        if (isset($criteria['ip'])) {
            $profiles = $this->indexer->filterByIp($profiles, $criteria['ip']);
        }

        if (isset($criteria['from'], $criteria['to'])) {
            $profiles = $this->indexer->filterByDateRange($profiles, $criteria['from'], $criteria['to']);
        }

        return \array_slice($profiles, 0, $limit);
    }

    /**
     * @return CollectorDataArray
     */
    public function getCollectorData(string $token, string $collectorName): array
    {
        $profile = $this->findProfile($token);

        if (null === $profile) {
            throw new ProfileNotFoundException(\sprintf('Profile not found for token: "%s"', $token));
        }

        if (!$profile->hasCollector($collectorName)) {
            throw new InvalidCollectorException(\sprintf('Collector "%s" not found in profile "%s"', $collectorName, $token));
        }

        $collectorData = $profile->getCollector($collectorName);
        $formatter = $this->collectorRegistry->get($collectorName);

        if (null === $formatter) {
            /** @var array<string, mixed> $data */
            $data = \is_array($collectorData) ? $collectorData : ['raw' => $collectorData];

            return [
                'name' => $collectorName,
                'data' => $data,
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
        $profile = $this->findProfile($token);

        if (null === $profile) {
            throw new ProfileNotFoundException(\sprintf('Profile not found for token: "%s"', $token));
        }

        return array_keys($profile->collectors);
    }

    public function getLatestProfile(): ?ProfileIndex
    {
        $profiles = $this->readIndex(1);

        return $profiles[0] ?? null;
    }

    /**
     * @return array<string|int, string> Map of context to directory path
     */
    private function getDirectories(): array
    {
        if (\is_string($this->profilerDir)) {
            return [0 => $this->profilerDir];
        }

        return $this->profilerDir;
    }

    private function readProfileFile(string $filePath, string $token, string|int|null $context): ?ProfileData
    {
        $data = file_get_contents($filePath);

        if (false === $data) {
            return null;
        }

        $decompressed = gzdecode($data);

        if (false === $decompressed) {
            return null;
        }

        // Register a temporary autoloader to handle missing profiler classes
        // Create a stub class for any missing Symfony profiler classes
        $autoloader = static function (string $class): void {
            if (str_contains($class, 'Symfony\\Component\\') || str_contains($class, 'Symfony\\Bridge\\')) {
                $namespace = substr($class, 0, (int) strrpos($class, '\\'));
                $className = substr($class, (int) strrpos($class, '\\') + 1);
                $code = "namespace $namespace { #[\\AllowDynamicProperties] class $className {} }";
                eval($code);
            }
        };

        spl_autoload_register($autoloader);
        try {
            $unserialized = unserialize($decompressed);
        } catch (\Throwable) {
            return null;
        } finally {
            spl_autoload_unregister($autoloader);
        }

        if (!\is_array($unserialized)) {
            return null;
        }

        $allProfiles = $this->indexer->indexProfiles($this->profilerDir);
        $profileIndex = null;

        foreach ($allProfiles as $index) {
            if ($index->token === $token) {
                $profileIndex = $index;
                break;
            }
        }

        if (null === $profileIndex) {
            $profileIndex = new ProfileIndex(
                token: $token,
                ip: '',
                method: '',
                url: '',
                time: time(),
                context: \is_string($context) ? $context : null,
            );
        }

        $collectors = [];
        $children = [];

        if (isset($unserialized['data']) && \is_array($unserialized['data'])) {
            $collectors = $unserialized['data'];
        } elseif (isset($unserialized['collectors']) && \is_array($unserialized['collectors'])) {
            $collectors = $unserialized['collectors'];
        }

        if (isset($unserialized['children']) && \is_array($unserialized['children'])) {
            $children = $unserialized['children'];
        }

        return new ProfileData(
            token: $token,
            index: $profileIndex,
            collectors: $collectors,
            children: $children,
        );
    }
}
