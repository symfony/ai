<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Capability;

use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Exception\ProfileNotFoundException;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Model\ProfileIndex;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * Tools for accessing Symfony profiler data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerTool
{
    /**
     * Number of grouped statements the triage returns. Triage answers "where is the load",
     * not "list every query" — the full grouped list stays behind the collector resource.
     */
    private const TRIAGE_QUERY_LIMIT = 5;

    public function __construct(
        private readonly ?ProfilerDataProvider $dataProvider = null,
    ) {
    }

    /**
     * @param int         $limit      Maximum number of profiles to return (use limit=1 to get the latest profile)
     * @param string|null $method     Filter by HTTP method (GET, POST, PUT, DELETE, PATCH)
     * @param string|null $url        Filter by URL path (partial match supported)
     * @param string|null $ip         Filter by client IP address
     * @param int|null    $statusCode Filter by HTTP response status code (e.g. 200, 404, 500)
     * @param string|null $context    Filter by Symfony kernel context
     * @param string|null $from       Start date filter for profile creation time
     * @param string|null $to         End date filter for profile creation time
     */
    #[MateTool(name: 'symfony-profiler-list', title: 'Symfony Profiler List', description: 'List and filter Symfony profiler profiles by HTTP method, URL, IP, status code, date range, or context. Profiles are sorted by most recent first, so limit=1 returns the latest profile. Returns summary data with resource_uri for fetching full details via the resource template.')]
    public function listProfiles(
        int $limit = 20,
        ?string $method = null,
        ?string $url = null,
        ?string $ip = null,
        ?int $statusCode = null,
        ?string $context = null,
        ?string $from = null,
        ?string $to = null,
    ): string {
        $dataProvider = $this->getDataProvider();
        $criteria = [
            'context' => $context,
            'method' => $method,
            'url' => $url,
            'ip' => $ip,
            'statusCode' => $statusCode,
            'from' => $from,
            'to' => $to,
        ];

        $profiles = $dataProvider->searchProfiles(array_filter($criteria), $limit);

        return ResponseEncoder::encodeUntrusted([
            'profiles' => array_values(array_map(
                static fn (ProfileIndex $profile): array => $profile->toArray(),
                $profiles,
            )),
        ]);
    }

    /**
     * @param string $token The unique profiler token identifying the profile
     */
    #[MateTool(name: 'symfony-profiler-get', title: 'Symfony Profiler Get', description: 'Get a specific profiler profile by its token. Returns detailed profile data including available collectors and resource_uri for accessing collector-specific data.')]
    public function getProfile(string $token): string
    {
        $profileData = $this->getDataProvider()->findProfile($token);

        if (null === $profileData) {
            throw new InvalidArgumentException(\sprintf('Profile with token "%s" not found', $token));
        }

        $profile = $profileData->getProfile();

        // The description promises the available collectors, and without them this returns the
        // same nine fields the listing already gave, leaving no way to reach the actual data.
        $collectors = [];
        foreach ($this->getDataProvider()->listAvailableCollectors($token) as $collectorName) {
            $collectors[] = [
                'name' => $collectorName,
                'uri' => \sprintf('symfony-profiler://profile/%s/%s', $token, $collectorName),
            ];
        }

        $data = [
            'token' => $profile->getToken(),
            'ip' => $profile->getIp(),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'time' => $profile->getTime(),
            'time_formatted' => date(\DateTimeInterface::ATOM, $profile->getTime()),
            'status_code' => $profile->getStatusCode(),
            'parent_token' => $profile->getParentToken(),
            'resource_uri' => \sprintf('symfony-profiler://profile/%s', $profile->getToken()),
            'collectors' => $collectors,
        ];

        if (null !== $profileData->getContext()) {
            $data['context'] = $profileData->getContext();
        }

        return ResponseEncoder::encodeUntrusted($data);
    }

    /**
     * @param string|null $url   URL path of the request to triage (partial match); the newest matching profile is used
     * @param string|null $token Exact profiler token to triage; takes precedence over url
     */
    #[MateTool(name: 'symfony-profiler-triage', title: 'Symfony Profiler Triage', description: 'Triage one request in a single call: returns query count, duplicate queries, the most expensive statements, total duration, whether an exception occurred and the logger error/warning counts for one profile. Start here instead of chaining symfony-profiler-list, symfony-profiler-get and a collector resource read. Without url or token it triages the most recent profile. Collectors the profile does not have are omitted.')]
    public function triage(?string $url = null, ?string $token = null): string
    {
        $dataProvider = $this->getDataProvider();
        $resolvedToken = $this->resolveTriageToken($url, $token);

        $profileData = $dataProvider->findProfile($resolvedToken);
        if (null === $profileData) {
            throw new ProfileNotFoundException(\sprintf('Profile not found for token: "%s"', $resolvedToken));
        }

        $profile = $profileData->getProfile();
        $available = $dataProvider->listAvailableCollectors($resolvedToken);

        $triage = [
            'token' => $profile->getToken(),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'status_code' => $profile->getStatusCode(),
            'resource_uri' => \sprintf('symfony-profiler://profile/%s', $profile->getToken()),
        ];

        $db = $this->getTriageCollectorData($resolvedToken, 'db', $available);
        if (null !== $db) {
            $section = $db['summary'];
            $queries = $db['data']['queries'] ?? null;
            if (\is_array($queries)) {
                $section['slowest_queries'] = $this->buildSlowestQueries($queries);
                $section['slowest_queries_truncated'] = \count($queries) > self::TRIAGE_QUERY_LIMIT;
            }

            $triage['db'] = $section;
        }

        foreach (['time', 'exception', 'logger'] as $collector) {
            $collectorData = $this->getTriageCollectorData($resolvedToken, $collector, $available);
            if (null !== $collectorData) {
                $triage[$collector] = $collectorData['summary'];
            }
        }

        return ResponseEncoder::encode($triage);
    }

    private function getDataProvider(): ProfilerDataProvider
    {
        if (null === $this->dataProvider) {
            throw new RuntimeException('Symfony profiler tools are not available in this Mate workspace.');
        }

        return $this->dataProvider;
    }

    private function resolveTriageToken(?string $url, ?string $token): string
    {
        if (null !== $token && '' !== $token) {
            return $token;
        }

        $dataProvider = $this->getDataProvider();

        if (null !== $url && '' !== $url) {
            $profiles = $dataProvider->searchProfiles(['url' => $url], 1);
            if ([] === $profiles) {
                throw new ProfileNotFoundException(\sprintf('No profile found for url "%s"', $url));
            }

            return $profiles[0]->getToken();
        }

        $latest = $dataProvider->getLatestProfile();
        if (null === $latest) {
            throw new ProfileNotFoundException('No profiles recorded; the profiler storage is empty.');
        }

        return $latest->getToken();
    }

    /**
     * Returns the collector data only when the profile actually has that collector and a
     * formatter produced a summary for it, so a missing collector is omitted instead of guessed.
     *
     * @param array<string> $available
     *
     * @return array{name: string, data: array<string, mixed>, summary: array<string, mixed>}|null
     */
    private function getTriageCollectorData(string $token, string $collector, array $available): ?array
    {
        if (!\in_array($collector, $available, true)) {
            return null;
        }

        $collectorData = $this->getDataProvider()->getCollectorData($token, $collector);
        if ([] === $collectorData['summary']) {
            return null;
        }

        return $collectorData;
    }

    /**
     * @param array<mixed> $queries
     *
     * @return list<array{sql: string, count: int, total_time_ms: float, avg_time_ms: float}>
     */
    private function buildSlowestQueries(array $queries): array
    {
        $slowest = [];
        foreach (\array_slice(array_values($queries), 0, self::TRIAGE_QUERY_LIMIT) as $query) {
            if (!\is_array($query)) {
                continue;
            }

            $slowest[] = [
                'sql' => (string) ($query['sql'] ?? ''),
                'count' => (int) ($query['count'] ?? 0),
                'total_time_ms' => (float) ($query['total_time_ms'] ?? 0.0),
                'avg_time_ms' => (float) ($query['avg_time_ms'] ?? 0.0),
            ];
        }

        return $slowest;
    }
}
