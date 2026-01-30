<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Model\ProfileIndex;

/**
 * Parses and filters profiler index CSV files.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
final class ProfileIndexer
{
    /**
     * @param string|array<string, string> $profilerDir
     *
     * @return array<ProfileIndex>
     */
    public function indexProfiles(string|array $profilerDir): array
    {
        if (\is_string($profilerDir)) {
            return $this->indexDirectory($profilerDir, null);
        }

        $profiles = [];
        foreach ($profilerDir as $context => $dir) {
            $profiles = array_merge($profiles, $this->indexDirectory($dir, $context));
        }

        return $profiles;
    }

    /**
     * @param array<ProfileIndex> $profiles
     *
     * @return array<ProfileIndex>
     */
    public function filterByMethod(array $profiles, string $method): array
    {
        return array_filter(
            $profiles,
            static fn (ProfileIndex $profile) => strtoupper($profile->method) === strtoupper($method)
        );
    }

    /**
     * @param array<ProfileIndex> $profiles
     *
     * @return array<ProfileIndex>
     */
    public function filterByStatusCode(array $profiles, int $statusCode): array
    {
        return array_filter(
            $profiles,
            static fn (ProfileIndex $profile) => $profile->statusCode === $statusCode
        );
    }

    /**
     * @param array<ProfileIndex> $profiles
     *
     * @return array<ProfileIndex>
     */
    public function filterByDateRange(array $profiles, string $from, string $to): array
    {
        $fromTime = strtotime($from);
        $toTime = strtotime($to);

        if (false === $fromTime || false === $toTime) {
            return $profiles;
        }

        return array_filter(
            $profiles,
            static fn (ProfileIndex $profile) => $profile->time >= $fromTime && $profile->time <= $toTime
        );
    }

    /**
     * @param array<ProfileIndex> $profiles
     *
     * @return array<ProfileIndex>
     */
    public function filterByUrl(array $profiles, string $urlPattern): array
    {
        return array_filter(
            $profiles,
            static fn (ProfileIndex $profile) => str_contains($profile->url, $urlPattern)
        );
    }

    /**
     * @param array<ProfileIndex> $profiles
     *
     * @return array<ProfileIndex>
     */
    public function filterByIp(array $profiles, string $ip): array
    {
        return array_filter(
            $profiles,
            static fn (ProfileIndex $profile) => $profile->ip === $ip
        );
    }

    /**
     * @param array<ProfileIndex> $profiles
     *
     * @return array<ProfileIndex>
     */
    public function filterByContext(array $profiles, string $context): array
    {
        return array_filter(
            $profiles,
            static fn (ProfileIndex $profile) => $profile->context === $context
        );
    }

    /**
     * @return array<ProfileIndex>
     */
    private function indexDirectory(string $dir, ?string $context): array
    {
        $indexFile = $dir.'/index.csv';

        if (!file_exists($indexFile) || !is_readable($indexFile)) {
            return [];
        }

        $profiles = [];
        $handle = fopen($indexFile, 'r');

        if (false === $handle) {
            return [];
        }

        try {
            while (false !== ($line = fgets($handle))) {
                $profile = $this->parseLine(trim($line), $context);
                if (null !== $profile) {
                    $profiles[] = $profile;
                }
            }
        } finally {
            fclose($handle);
        }

        return $profiles;
    }

    private function parseLine(string $line, ?string $context): ?ProfileIndex
    {
        if ('' === $line) {
            return null;
        }

        // CSV format: token,ip,method,url,time,parent,statusCode,type
        $parts = str_getcsv($line, ',', '"', '\\');

        if (\count($parts) < 5) {
            return null;
        }

        $token = $parts[0] ?? '';
        $ip = $parts[1] ?? '';
        $method = $parts[2] ?? '';
        $url = $parts[3] ?? '';
        $time = (int) ($parts[4] ?? 0);
        $parent = $parts[5] ?? null;
        $statusCode = isset($parts[6]) && '' !== $parts[6] ? (int) $parts[6] : null;
        $type = $parts[7] ?? null;

        if ('' === $token) {
            return null;
        }

        return new ProfileIndex(
            token: $token,
            ip: $ip,
            method: $method,
            url: $url,
            time: $time,
            statusCode: $statusCode,
            parentToken: null !== $parent && '' !== $parent ? $parent : null,
            context: $context,
            type: null !== $type && '' !== $type ? $type : null,
        );
    }
}
