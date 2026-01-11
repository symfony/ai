<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\ProfilerResourceTemplate;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerResourceTemplateTest extends TestCase
{
    private ProfilerResourceTemplate $template;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__.'/../Fixtures/profiler';
        $registry = new CollectorRegistry([]);
        $provider = new ProfilerDataProvider($this->fixtureDir, $registry);

        $this->template = new ProfilerResourceTemplate($provider);
    }

    public function testGetProfileResourceReturnsValidStructure()
    {
        $resource = $this->template->getProfileResource('abc123');

        $this->assertArrayHasKey('uri', $resource);
        $this->assertArrayHasKey('mimeType', $resource);
        $this->assertArrayHasKey('text', $resource);
        $this->assertSame('symfony-profiler://profile/abc123', $resource['uri']);
        $this->assertSame('application/json', $resource['mimeType']);
    }

    public function testGetProfileResourceIncludesMetadata()
    {
        $resource = $this->template->getProfileResource('abc123');

        $data = json_decode($resource['text'], true);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('method', $data);
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('status_code', $data);
        $this->assertArrayHasKey('collectors', $data);

        $this->assertSame('abc123', $data['token']);
    }

    public function testGetProfileResourceIncludesCollectorUris()
    {
        $resource = $this->template->getProfileResource('abc123');

        $data = json_decode($resource['text'], true);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('collectors', $data);
        $this->assertIsArray($data['collectors']);
        $this->assertNotEmpty($data['collectors']);

        // Check collector structure
        foreach ($data['collectors'] as $collector) {
            $this->assertArrayHasKey('name', $collector);
            $this->assertArrayHasKey('uri', $collector);
            $this->assertStringStartsWith('symfony-profiler://profile/abc123/', $collector['uri']);
        }
    }

    public function testGetProfileResourceForNonExistentProfileReturnsError()
    {
        $resource = $this->template->getProfileResource('nonexistent');

        $data = json_decode($resource['text'], true);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Profile not found', $data['error']);
    }

    public function testGetCollectorResourceReturnsValidStructure()
    {
        $resource = $this->template->getCollectorResource('abc123', 'request');

        $this->assertArrayHasKey('uri', $resource);
        $this->assertArrayHasKey('mimeType', $resource);
        $this->assertArrayHasKey('text', $resource);
        $this->assertSame('symfony-profiler://profile/abc123/request', $resource['uri']);
    }

    public function testGetCollectorResourceReturnsJsonContent()
    {
        $resource = $this->template->getCollectorResource('abc123', 'request');

        $data = json_decode($resource['text'], true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertSame('request', $data['name']);
    }

    public function testResourcesReturnNonEmptyText()
    {
        $resources = [
            $this->template->getProfileResource('abc123'),
            $this->template->getCollectorResource('abc123', 'request'),
        ];

        foreach ($resources as $resource) {
            $this->assertNotEmpty($resource['text']);
            $this->assertIsString($resource['text']);
        }
    }

    public function testResourcesHandleErrorsGracefully()
    {
        $resource = $this->template->getCollectorResource('nonexistent', 'request');

        $this->assertArrayHasKey('text', $resource);
        $data = json_decode($resource['text'], true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
    }
}
