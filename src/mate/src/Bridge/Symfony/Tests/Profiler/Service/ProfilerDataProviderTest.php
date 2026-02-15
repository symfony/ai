<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Exception\InvalidCollectorException;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Exception\ProfileNotFoundException;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerDataProviderTest extends TestCase
{
    private ProfilerDataProvider $provider;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__.'/../../Fixtures/profiler';
        $registry = new CollectorRegistry([]);

        $this->provider = new ProfilerDataProvider(
            $this->fixtureDir,
            $registry
        );
    }

    public function testReadIndexReturnsAllProfiles()
    {
        $profiles = $this->provider->readIndex();

        $this->assertCount(3, $profiles);
        $this->assertSame('ghi789', $profiles[0]->token);
        $this->assertSame('def456', $profiles[1]->token);
        $this->assertSame('abc123', $profiles[2]->token);
    }

    public function testReadIndexWithLimit()
    {
        $profiles = $this->provider->readIndex(2);

        $this->assertCount(2, $profiles);
        $this->assertSame('ghi789', $profiles[0]->token);
        $this->assertSame('def456', $profiles[1]->token);
    }

    public function testFindProfileReturnsProfileData()
    {
        $profileData = $this->provider->findProfile('abc123');

        $this->assertNotNull($profileData);
        $this->assertSame('abc123', $profileData->profile->getToken());
        $this->assertInstanceOf(Profile::class, $profileData->profile);
        $this->assertIsArray($profileData->profile->getCollectors());
    }

    public function testFindProfileReturnsNullForNonExistentToken()
    {
        $profile = $this->provider->findProfile('nonexistent');

        $this->assertNull($profile);
    }

    public function testFindProfileUsesNestedDirectoryStructure()
    {
        // Token 'abc123': last 2 chars = '23', next-to-last 2 chars = 'c1'
        // Should find file at: profiler/23/c1/abc123
        $profileData = $this->provider->findProfile('abc123');

        $this->assertNotNull($profileData);
        $this->assertSame('abc123', $profileData->profile->getToken());
    }

    public function testSearchProfilesWithoutCriteria()
    {
        $profiles = $this->provider->searchProfiles([]);

        $this->assertCount(3, $profiles);
    }

    public function testSearchProfilesByMethod()
    {
        $profiles = $this->provider->searchProfiles(['method' => 'POST']);

        $this->assertCount(1, $profiles);
        $this->assertSame('def456', $profiles[0]->token);
    }

    public function testSearchProfilesByStatusCode()
    {
        $profiles = $this->provider->searchProfiles(['statusCode' => 404]);

        $this->assertCount(1, $profiles);
        $this->assertSame('ghi789', $profiles[0]->token);
    }

    public function testSearchProfilesByUrl()
    {
        $profiles = $this->provider->searchProfiles(['url' => 'users']);

        $this->assertCount(2, $profiles);
    }

    public function testSearchProfilesByIp()
    {
        $profiles = $this->provider->searchProfiles(['ip' => '127.0.0.1']);

        $this->assertCount(2, $profiles);
    }

    public function testSearchProfilesWithLimit()
    {
        $profiles = $this->provider->searchProfiles(['method' => 'GET'], 1);

        $this->assertCount(1, $profiles);
    }

    public function testGetCollectorDataThrowsExceptionForNonExistentProfile()
    {
        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage('Profile not found for token: "nonexistent"');

        $this->provider->getCollectorData('nonexistent', 'request');
    }

    public function testGetCollectorDataThrowsExceptionForInvalidCollector()
    {
        $this->expectException(InvalidCollectorException::class);

        $this->provider->getCollectorData('abc123', 'nonexistent');
    }

    public function testGetCollectorDataReturnsFormattedData()
    {
        $data = $this->provider->getCollectorData('abc123', 'request');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertSame('request', $data['name']);
    }

    public function testListAvailableCollectorsThrowsExceptionForNonExistentProfile()
    {
        $this->expectException(ProfileNotFoundException::class);

        $this->provider->listAvailableCollectors('nonexistent');
    }

    public function testListAvailableCollectorsReturnsCollectorNames()
    {
        $collectors = $this->provider->listAvailableCollectors('abc123');

        $this->assertIsArray($collectors);
        $this->assertContains('request', $collectors);
    }

    public function testListAvailableCollectorsReturnsShortNamesNotClassNames()
    {
        $collectors = $this->provider->listAvailableCollectors('abc123');

        foreach ($collectors as $collectorName) {
            $this->assertIsString($collectorName);
            $this->assertStringNotContainsString('\\', $collectorName);
            $this->assertDoesNotMatchRegularExpression('/^[A-Z]/', $collectorName);
        }

        $this->assertContains('request', $collectors);
    }

    public function testGetCollectorDataWorksWithShortNames()
    {
        $data = $this->provider->getCollectorData('abc123', 'request');

        $this->assertIsArray($data);
        $this->assertSame('request', $data['name']);

        $this->assertArrayHasKey('name', $data);
        $this->assertStringNotContainsString('\\', $data['name']);
    }

    public function testGetLatestProfileReturnsFirstProfile()
    {
        $profile = $this->provider->getLatestProfile();

        $this->assertNotNull($profile);
        $this->assertSame('ghi789', $profile->token);
    }

    public function testGetLatestProfileReturnsNullWhenNoProfiles()
    {
        $registry = new CollectorRegistry([]);
        $emptyDir = sys_get_temp_dir().'/profiler_empty_'.uniqid();
        mkdir($emptyDir);

        try {
            $provider = new ProfilerDataProvider($emptyDir, $registry);

            $profile = $provider->getLatestProfile();

            $this->assertNull($profile);
        } finally {
            rmdir($emptyDir);
        }
    }

    public function testSupportsMultipleProfilerDirectories()
    {
        $registry = new CollectorRegistry([]);
        $provider = new ProfilerDataProvider(
            ['context1' => $this->fixtureDir],
            $registry
        );

        $profiles = $provider->readIndex();

        $this->assertCount(3, $profiles);
        $this->assertSame('context1', $profiles[0]->context);
    }
}
