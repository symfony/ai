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
use Symfony\AI\Mate\Bridge\Symfony\Capability\ProfilerTool;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerToolTest extends TestCase
{
    private ProfilerTool $tool;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__.'/../Fixtures/profiler';
        $registry = new CollectorRegistry([]);
        $provider = new ProfilerDataProvider($this->fixtureDir, $registry);

        $this->tool = new ProfilerTool($provider);
    }

    public function testListProfilesReturnsAllProfiles()
    {
        $profiles = $this->tool->listProfiles();

        $this->assertCount(3, $profiles);
        $this->assertArrayHasKey('token', $profiles[0]);
        $this->assertArrayHasKey('time_formatted', $profiles[0]);
        $this->assertSame('ghi789', $profiles[0]['token']);
    }

    public function testListProfilesWithLimit()
    {
        $profiles = $this->tool->listProfiles(limit: 2);

        $this->assertCount(2, $profiles);
    }

    public function testListProfilesFilterByMethod()
    {
        $profiles = $this->tool->listProfiles(method: 'POST');

        $this->assertCount(1, $profiles);
        $this->assertSame('def456', $profiles[0]['token']);
    }

    public function testListProfilesFilterByStatusCode()
    {
        $profiles = $this->tool->listProfiles(statusCode: 404);

        $this->assertCount(1, $profiles);
        $this->assertSame('ghi789', $profiles[0]['token']);
    }

    public function testListProfilesFilterByUrl()
    {
        $profiles = $this->tool->listProfiles(url: 'users');

        $this->assertCount(2, $profiles);
    }

    public function testListProfilesFilterByIp()
    {
        $profiles = $this->tool->listProfiles(ip: '127.0.0.1');

        $this->assertCount(2, $profiles);
    }

    public function testGetLatestProfileReturnsFirstProfile()
    {
        $profile = $this->tool->getLatestProfile();

        $this->assertNotNull($profile);
        $this->assertArrayHasKey('token', $profile);
        $this->assertArrayHasKey('time_formatted', $profile);
        $this->assertArrayHasKey('resource_uri', $profile);
        $this->assertSame('ghi789', $profile['token']);
    }

    public function testGetLatestProfileIncludesResourceUri()
    {
        $profile = $this->tool->getLatestProfile();

        $this->assertNotNull($profile);
        $this->assertArrayHasKey('resource_uri', $profile);
        $this->assertSame(
            'symfony-profiler://profile/'.$profile['token'],
            $profile['resource_uri']
        );
    }

    public function testSearchProfilesWithoutCriteria()
    {
        $profiles = $this->tool->searchProfiles();

        $this->assertCount(3, $profiles);
    }

    public function testSearchProfilesByMethod()
    {
        $profiles = $this->tool->searchProfiles(method: 'GET');

        $this->assertCount(2, $profiles);
    }

    public function testSearchProfilesByStatusCode()
    {
        $profiles = $this->tool->searchProfiles(statusCode: 200);

        $this->assertCount(1, $profiles);
        $this->assertSame('abc123', $profiles[0]['token']);
    }

    public function testSearchProfilesByRoute()
    {
        $profiles = $this->tool->searchProfiles(route: '/api/users');

        $this->assertCount(2, $profiles);
    }

    public function testSearchProfilesWithLimit()
    {
        $profiles = $this->tool->searchProfiles(limit: 1);

        $this->assertCount(1, $profiles);
    }

    public function testGetProfileReturnsProfileWithResourceUri()
    {
        $profile = $this->tool->getProfile('abc123');

        $this->assertArrayHasKey('token', $profile);
        $this->assertArrayHasKey('resource_uri', $profile);
        $this->assertSame('abc123', $profile['token']);
        $this->assertSame('symfony-profiler://profile/abc123', $profile['resource_uri']);
    }

    public function testGetProfileThrowsExceptionForNonExistentToken()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile with token "nonexistent" not found');

        $this->tool->getProfile('nonexistent');
    }

    public function testListProfilesIncludesResourceUri()
    {
        $profiles = $this->tool->listProfiles();

        $this->assertCount(3, $profiles);
        foreach ($profiles as $profile) {
            $this->assertArrayHasKey('resource_uri', $profile);
            $this->assertStringStartsWith('symfony-profiler://profile/', $profile['resource_uri']);
            $this->assertSame(
                'symfony-profiler://profile/'.$profile['token'],
                $profile['resource_uri']
            );
        }
    }

    public function testSearchProfilesIncludesResourceUri()
    {
        $profiles = $this->tool->searchProfiles();

        foreach ($profiles as $profile) {
            $this->assertArrayHasKey('resource_uri', $profile);
            $this->assertStringStartsWith('symfony-profiler://profile/', $profile['resource_uri']);
        }
    }

    public function testListProfilesReturnsIntegerKeys()
    {
        $profiles = $this->tool->listProfiles();

        $keys = array_keys($profiles);
        $this->assertSame([0, 1, 2], $keys);
    }

    public function testSearchProfilesReturnsIntegerKeys()
    {
        $profiles = $this->tool->searchProfiles();

        $keys = array_keys($profiles);
        $this->assertSame([0, 1, 2], $keys);
    }
}
