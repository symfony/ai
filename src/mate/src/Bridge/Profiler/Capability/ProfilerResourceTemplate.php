<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Profiler\Capability;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Symfony\AI\Mate\Bridge\Profiler\Service\ProfilerDataProvider;

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
        uriTemplate: 'profiler://profile/{token}',
        name: 'profile-data',
        description: 'Full profile details including metadata (method, url, status, time, ip) and complete list of available collectors with URIs for accessing collector-specific data'
    )]
    public function getProfileResource(string $token): array
    {
        $profile = $this->dataProvider->findProfile($token);
        if (null === $profile) {
            return [
                'uri' => "profiler://profile/{$token}",
                'mimeType' => 'application/json',
                'text' => json_encode(['error' => 'Profile not found'], \JSON_PRETTY_PRINT) ?: '{}',
            ];
        }

        $collectors = $this->dataProvider->listAvailableCollectors($token);

        $collectorResources = [];
        foreach ($collectors as $collectorName) {
            $collectorResources[] = [
                'name' => $collectorName,
                'uri' => \sprintf('profiler://profile/%s/%s', $token, $collectorName),
            ];
        }

        $data = [
            'token' => $profile->token,
            'method' => $profile->index->method,
            'url' => $profile->index->url,
            'status_code' => $profile->index->statusCode,
            'time' => $profile->index->time,
            'ip' => $profile->index->ip,
            'parent_profile' => $profile->index->parentToken ? \sprintf('profiler://profile/%s', $profile->index->parentToken) : null,
            'collectors' => $collectorResources,
        ];

        if (null !== $profile->index->context) {
            $data['context'] = $profile->index->context;
        }

        return [
            'uri' => "profiler://profile/{$token}",
            'mimeType' => 'application/json',
            'text' => json_encode($data, \JSON_PRETTY_PRINT) ?: '{}',
        ];
    }

    /**
     * @return ProfileResourceData
     */
    #[McpResourceTemplate(
        uriTemplate: 'profiler://profile/{token}/{collector}',
        name: 'collector-data',
        description: 'Detailed collector-specific data (e.g., request parameters, response content, database queries, events, exceptions). Use profiler://profile/{token} resource to discover available collectors'
    )]
    public function getCollectorResource(string $token, string $collector): array
    {
        try {
            $data = $this->dataProvider->getCollectorData($token, $collector);

            return [
                'uri' => "profiler://profile/{$token}/{$collector}",
                'mimeType' => 'application/json',
                'text' => json_encode($data, \JSON_PRETTY_PRINT) ?: '{}',
            ];
        } catch (\Throwable $e) {
            return [
                'uri' => "profiler://profile/{$token}/{$collector}",
                'mimeType' => 'application/json',
                'text' => json_encode(['error' => $e->getMessage()]) ?: '{}',
            ];
        }
    }
}
