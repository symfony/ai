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

use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Model\ProfileIndex;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\AI\Mate\Exception\InvalidArgumentException;

/**
 * MCP tools for accessing Symfony profiler data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @phpstan-import-type ProfileIndexData from ProfileIndex
 */
final class ProfilerTool
{
    public function __construct(
        private readonly ProfilerDataProvider $dataProvider,
    ) {
    }

    /**
     * @return list<ProfileIndexData>
     */
    #[McpTool('symfony-profiler-list', 'List available profiler profiles. Returns summary data with resource_uri field - use the resource_uri to fetch full profile details including collectors')]
    public function listProfiles(
        int $limit = 20,
        ?string $method = null,
        ?string $url = null,
        ?string $ip = null,
        ?int $statusCode = null,
        ?string $context = null,
    ): array {
        $criteria = [
            'context' => $context,
            'method' => $method,
            'url' => $url,
            'ip' => $ip,
            'statusCode' => $statusCode,
        ];

        $profiles = $this->dataProvider->searchProfiles(array_filter($criteria), $limit);

        return array_values(array_map(
            static fn (ProfileIndex $profile): array => $profile->toArray(),
            $profiles,
        ));
    }

    /**
     * @return ProfileIndexData|null
     */
    #[McpTool('symfony-profiler-latest', 'Get the latest profiler profile. Returns summary data with resource_uri field - use the resource_uri to fetch full profile details including collectors')]
    public function getLatestProfile(): ?array
    {
        $profile = $this->dataProvider->getLatestProfile();

        return $profile?->toArray();
    }

    /**
     * @return list<ProfileIndexData>
     */
    #[McpTool('symfony-profiler-search', 'Search profiles by criteria. Returns summary data with resource_uri field - use the resource_uri to fetch full profile details including collectors')]
    public function searchProfiles(
        ?string $route = null,
        ?string $method = null,
        ?int $statusCode = null,
        ?string $from = null,
        ?string $to = null,
        ?string $context = null,
        int $limit = 20,
    ): array {
        $criteria = [
            'context' => $context,
            'url' => $route,
            'method' => $method,
            'statusCode' => $statusCode,
            'from' => $from,
            'to' => $to,
        ];

        $profiles = $this->dataProvider->searchProfiles(array_filter($criteria), $limit);

        return array_values(array_map(
            static fn (ProfileIndex $profile): array => $profile->toArray(),
            $profiles,
        ));
    }

    /**
     * @return ProfileIndexData
     */
    #[McpTool('symfony-profiler-get', 'Get a specific profile by token. Returns summary data with resource_uri field - use the resource_uri to fetch full profile details including collectors')]
    public function getProfile(string $token): array
    {
        $profileData = $this->dataProvider->findProfile($token);

        if (null === $profileData) {
            throw new InvalidArgumentException(\sprintf('Profile with token "%s" not found', $token));
        }

        $profile = $profileData->profile;
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
        ];

        if (null !== $profileData->context) {
            $data['context'] = $profileData->context;
        }

        return $data;
    }
}
