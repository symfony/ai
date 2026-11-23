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

use Symfony\AI\Mate\Attribute\MateResourceTemplate;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\ConfigCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\DoctrineCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\ExceptionCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\HttpClientCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\LoggerCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\MailerCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\MemoryCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\RequestCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\RouterCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\TimeCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\TranslationCollectorFormatter;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * Resource templates for accessing Symfony profiler data.
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
    public const PROFILE_OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'token' => ['type' => 'string'],
            'method' => ['type' => 'string'],
            'url' => ['type' => 'string'],
            'status_code' => ['type' => ['integer', 'null']],
            'time' => ['type' => 'integer', 'description' => 'Unix timestamp.'],
            'ip' => ['type' => ['string', 'null']],
            'parent_profile' => ['type' => ['string', 'null'], 'description' => 'URI of the parent profile.'],
            'collectors' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'uri' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'uri'],
                ],
            ],
            'context' => ['type' => 'string', 'description' => 'Profiler directory the profile came from. Only present with several profiler directories.'],
        ],
        'required' => ['token', 'method', 'url', 'status_code', 'time', 'ip', 'parent_profile', 'collectors'],
    ];

    private const SUMMARY_SCHEMA = ['type' => 'object', 'description' => 'Short triage view of the collector, its keys depend on the collector.'];

    public function __construct(
        private readonly ?ProfilerDataProvider $dataProvider = null,
    ) {
    }

    /**
     * @return ProfileResourceData
     */
    #[MateResourceTemplate(
        uriTemplate: 'symfony-profiler://profile/{token}',
        name: 'symfony-profile-data',
        description: 'Full profile details including metadata (method, url, status, time, ip) and complete list of available collectors with URIs for accessing collector-specific data',
        outputSchema: self::PROFILE_OUTPUT_SCHEMA,
    )]
    public function getProfileResource(string $token): array
    {
        $dataProvider = $this->getDataProvider();
        $profileData = $dataProvider->findProfile($token);
        if (null === $profileData) {
            return [
                'uri' => "symfony-profiler://profile/{$token}",
                'mimeType' => 'text/plain',
                'text' => ResponseEncoder::encode(['error' => 'Profile not found']),
            ];
        }

        $profile = $profileData->getProfile();
        $collectors = $dataProvider->listAvailableCollectors($token);

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

        if (null !== $profileData->getContext()) {
            $data['context'] = $profileData->getContext();
        }

        return [
            'uri' => "symfony-profiler://profile/{$token}",
            'mimeType' => 'text/plain',
            'text' => ResponseEncoder::encodeUntrusted($data),
        ];
    }

    /**
     * @return ProfileResourceData
     */
    #[MateResourceTemplate(
        uriTemplate: 'symfony-profiler://profile/{token}/{collector}',
        name: 'symfony-collector-data',
        description: 'Detailed collector-specific data (e.g., request parameters, response content, database queries, events, exceptions). Use symfony-profiler://profile/{token} resource to discover available collectors',
        outputSchema: ['oneOf' => [
            ['title' => 'config', 'type' => 'object', 'properties' => ['name' => ['enum' => ['config']], 'data' => ConfigCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'request', 'type' => 'object', 'properties' => ['name' => ['enum' => ['request']], 'data' => RequestCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'router', 'type' => 'object', 'properties' => ['name' => ['enum' => ['router']], 'data' => RouterCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'exception', 'type' => 'object', 'properties' => ['name' => ['enum' => ['exception']], 'data' => ExceptionCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'mailer', 'type' => 'object', 'properties' => ['name' => ['enum' => ['mailer']], 'data' => MailerCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'translation', 'type' => 'object', 'properties' => ['name' => ['enum' => ['translation']], 'data' => TranslationCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'db', 'type' => 'object', 'properties' => ['name' => ['enum' => ['db']], 'data' => DoctrineCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'time', 'type' => 'object', 'properties' => ['name' => ['enum' => ['time']], 'data' => TimeCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'logger', 'type' => 'object', 'properties' => ['name' => ['enum' => ['logger']], 'data' => LoggerCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'memory', 'type' => 'object', 'properties' => ['name' => ['enum' => ['memory']], 'data' => MemoryCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'http_client', 'type' => 'object', 'properties' => ['name' => ['enum' => ['http_client']], 'data' => HttpClientCollectorFormatter::OUTPUT_SCHEMA, 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
            ['title' => 'other', 'description' => 'Collectors without a formatter (events, twig, cache, messenger, serializer, ...): the raw collector data, unredacted structure.', 'type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'data' => ['type' => 'object', 'properties' => ['raw' => []], 'required' => ['raw']], 'summary' => self::SUMMARY_SCHEMA], 'required' => ['name', 'data', 'summary']],
        ]],
    )]
    public function getCollectorResource(string $token, string $collector): array
    {
        try {
            $data = $this->getDataProvider()->getCollectorData($token, $collector);

            return [
                'uri' => "symfony-profiler://profile/{$token}/{$collector}",
                'mimeType' => 'text/plain',
                'text' => ResponseEncoder::encodeUntrusted($data),
            ];
        } catch (\Throwable $e) {
            return [
                'uri' => "symfony-profiler://profile/{$token}/{$collector}",
                'mimeType' => 'text/plain',
                'text' => ResponseEncoder::encode(['error' => $e->getMessage()]),
            ];
        }
    }

    private function getDataProvider(): ProfilerDataProvider
    {
        if (null === $this->dataProvider) {
            throw new RuntimeException('Symfony profiler resources are not available in this Mate workspace.');
        }

        return $this->dataProvider;
    }
}
