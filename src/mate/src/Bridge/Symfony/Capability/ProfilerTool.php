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

    private function getDataProvider(): ProfilerDataProvider
    {
        if (null === $this->dataProvider) {
            throw new RuntimeException('Symfony profiler tools are not available in this Mate workspace.');
        }

        return $this->dataProvider;
    }
}
