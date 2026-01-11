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

use Mcp\Capability\Attribute\McpResourceTemplate;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;

/**
 * MCP resource templates for accessing Symfony profiler data.
 *
 * @phpstan-type ProfileResourceData array{
 *     uri: string,
 *     mimeType: string,
 *     text: string,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerResourceTemplate
{
    public function __construct(
        private readonly ProfilerDataProvider $dataProvider,
    ) {
    }

    /**
     * @return ProfileResourceData
     */
    #[McpResourceTemplate(
        uriTemplate: 'symfony-profiler://profile/{token}',
        name: 'symfony-profile-data',
        description: 'Full profile details including metadata (method, url, status, time, ip) and complete list of available collectors with URIs for accessing collector-specific data'
    )]
    public function getProfileResource(string $token): array
    {
        $profileData = $this->dataProvider->findProfile($token);
        if (null === $profileData) {
            return [
                'uri' => "symfony-profiler://profile/{$token}",
                'mimeType' => 'application/json',
                'text' => json_encode(['error' => 'Profile not found'], \JSON_PRETTY_PRINT) ?: '{}',
            ];
        }

        $profile = $profileData->profile;
        $collectors = $this->dataProvider->listAvailableCollectors($token);

        $collectorResources = [];
        foreach ($collectors as $collectorName) {
            $collectorResources[] = [
                'name' => $collectorName,
                'uri' => \sprintf('symfony-profiler://profile/%s/%s', $token, $collectorName),
            ];
        }

        $data = [
            'token' => $profile->getToken(),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'status_code' => $profile->getStatusCode(),
            'time' => $profile->getTime(),
            'ip' => $profile->getIp(),
            'parent_profile' => $profile->getParentToken() ? \sprintf('symfony-profiler://profile/%s', $profile->getParentToken()) : null,
            'collectors' => $collectorResources,
        ];

        if (null !== $profileData->context) {
            $data['context'] = $profileData->context;
        }

        return [
            'uri' => "symfony-profiler://profile/{$token}",
            'mimeType' => 'application/json',
            'text' => json_encode($data, \JSON_PRETTY_PRINT) ?: '{}',
        ];
    }

    /**
     * @return ProfileResourceData
     */
    #[McpResourceTemplate(
        uriTemplate: 'symfony-profiler://profile/{token}/{collector}',
        name: 'symfony-collector-data',
        description: 'Detailed collector-specific data (e.g., request parameters, response content, database queries, events, exceptions). Use symfony-profiler://profile/{token} resource to discover available collectors'
    )]
    public function getCollectorResource(string $token, string $collector): array
    {
        try {
            $data = $this->dataProvider->getCollectorData($token, $collector);

            return [
                'uri' => "symfony-profiler://profile/{$token}/{$collector}",
                'mimeType' => 'application/json',
                'text' => json_encode($data, \JSON_PRETTY_PRINT) ?: '{}',
            ];
        } catch (\Throwable $e) {
            return [
                'uri' => "symfony-profiler://profile/{$token}/{$collector}",
                'mimeType' => 'application/json',
                'text' => json_encode(['error' => $e->getMessage()]) ?: '{}',
            ];
        }
    }
}
