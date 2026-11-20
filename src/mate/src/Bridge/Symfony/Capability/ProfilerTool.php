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
    private const MAX_FAILING_GROUPS = 20;

    public function __construct(
        private readonly ?ProfilerDataProvider $dataProvider = null,
    ) {
    }

    /**
     * @param int         $limit      Maximum number of profiles to return (use limit=1 to get the latest profile, 0 or more than 500 for the maximum of 500)
     * @param string|null $method     Filter by HTTP method (GET, POST, PUT, DELETE, PATCH)
     * @param string|null $url        Filter by URL path (partial match supported)
     * @param string|null $ip         Filter by client IP address
     * @param int|null    $statusCode Filter by HTTP response status code (e.g. 200, 404, 500)
     * @param string|null $context    Filter by Symfony kernel context
     * @param string|null $from       Start date filter for profile creation time
     * @param string|null $to         End date filter for profile creation time
     */
    #[MateTool(name: 'symfony-profiler-list', title: 'Symfony Profiler List', description: 'List and filter Symfony profiler profiles by HTTP method, URL, IP, status code, date range, or context. Returns total (the number of matching profiles) and truncated, then the profiles, newest first (limit=1 returns the latest), each with summary data and a resource_uri for the full details. At most 500 profiles are returned. When truncated is true, narrow the filters instead of listing everything to count or aggregate.')]
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
        if ($limit < 0) {
            throw new InvalidArgumentException('limit must be 0 or greater.');
        }

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

        $page = $dataProvider->searchProfiles(array_filter($criteria), $limit);

        // Counts come first so an agent that stops reading early still sees the truncation.
        $result = [
            'total' => $page['total'],
            'truncated' => $page['total'] > \count($page['profiles']),
            'profiles' => array_values(array_map(
                static fn (ProfileIndex $profile): array => $profile->toArray(),
                $page['profiles'],
            )),
        ];

        return ResponseEncoder::encodeUntrusted($result);
    }

    /**
     * @param string $token The unique profiler token identifying the profile
     */
    #[MateTool(name: 'symfony-profiler-get', title: 'Symfony Profiler Get', description: 'Get a specific profiler profile by its token. Returns detailed profile data including available collectors and resource_uri for accessing collector-specific data. Collector data (form, validator, request, time, db, events, cache, security, ...) is read through those URIs, with the `resources:read <uri>` command.')]
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
     * @param int         $limit   How many of the latest top-level requests to look at, 0 for all of them
     * @param string|null $context Only look at this Symfony kernel context
     */
    #[MateTool(name: 'symfony-profiler-summary', title: 'Symfony Profiler Summary', description: 'Summarize the latest recorded requests in one call: how many "requests" there were, how many "failed" (status 400 or above), and which requests fail most often as "failing_requests_by_count" (key is "METHOD STATUS path" without the query string, value is the count, highest first, at most 20 groups, with "failing_requests_truncated" true when more exist). Sub-requests and console commands are not counted. Only the latest "limit" requests are looked at, and "truncated" says whether older ones were left out; limit=0 looks at all of them. Use it to answer how many requests failed and which fails most, then list the cases with symfony-profiler-list.')]
    public function summarizeTraffic(int $limit = 500, ?string $context = null): string
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('limit must be 0 or greater.');
        }

        $requests = 0;
        $truncated = false;
        $failing = [];

        foreach ($this->getDataProvider()->readIndex() as $profile) {
            if (null !== $context && $context !== $profile->getContext()) {
                continue;
            }

            $parentToken = $profile->getParentToken();
            if (null !== $parentToken && '' !== $parentToken) {
                continue;
            }

            $type = $profile->getType();
            if (null !== $type && 'request' !== $type) {
                continue;
            }

            if ($limit > 0 && $requests >= $limit) {
                $truncated = true;
                break;
            }

            ++$requests;

            $statusCode = $profile->getStatusCode();
            if (null === $statusCode || $statusCode < 400) {
                continue;
            }

            $url = $profile->getUrl();
            $key = \sprintf('%s %d %s', $profile->getMethod(), $statusCode, (string) parse_url($url, \PHP_URL_PATH));
            $failing[$key] = ($failing[$key] ?? 0) + 1;
        }

        uksort($failing, static fn (string $a, string $b): int => [$failing[$b], $a] <=> [$failing[$a], $b]);

        $failed = array_sum($failing);
        $failingTruncated = \count($failing) > self::MAX_FAILING_GROUPS;

        return ResponseEncoder::encodeUntrusted([
            'requests' => $requests,
            'failed' => $failed,
            'truncated' => $truncated,
            'failing_requests_truncated' => $failingTruncated,
            'failing_requests_by_count' => \array_slice($failing, 0, self::MAX_FAILING_GROUPS, true),
        ]);
    }

    private function getDataProvider(): ProfilerDataProvider
    {
        if (null === $this->dataProvider) {
            throw new RuntimeException('Symfony profiler tools are not available in this Mate workspace.');
        }

        return $this->dataProvider;
    }
}
