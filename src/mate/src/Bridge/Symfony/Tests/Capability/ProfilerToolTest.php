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

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\ProfilerTool;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\RuntimeException;

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
        $result = $this->decodeUntrusted($this->tool->listProfiles());

        $this->assertArrayHasKey('profiles', $result);
        $profiles = $result['profiles'];
        $this->assertCount(3, $profiles);
        $this->assertArrayHasKey('token', $profiles[0]);
        $this->assertArrayHasKey('time_formatted', $profiles[0]);
        $this->assertSame('ghi789', $profiles[0]['token']);
    }

    public function testListProfilesWithLimit()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 2));

        $this->assertArrayHasKey('profiles', $result);
        $this->assertCount(2, $result['profiles']);
    }

    public function testListProfilesSaysWhenItReturnedOnlyAPage()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 2));

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['returned']);
        $this->assertSame(2, $result['limit']);
        $this->assertTrue($result['has_more']);
        $this->assertSame('Showing the 2 most recent of 3 matching profiles. Pass --limit=3 to list all of them.', $result['more']);
        $this->assertCount(2, $result['profiles']);
    }

    public function testListProfilesSaysWhenItReturnedEverything()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles());

        $this->assertSame(3, $result['total']);
        $this->assertSame(3, $result['returned']);
        $this->assertSame(20, $result['limit']);
        $this->assertFalse($result['has_more']);
        $this->assertArrayNotHasKey('more', $result);
    }

    public function testListProfilesWithLimitEqualToTotalHasNoMore()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 3));

        $this->assertSame(3, $result['returned']);
        $this->assertFalse($result['has_more']);
    }

    public function testListProfilesPutsTheCountsBeforeTheProfiles()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 1));

        $this->assertSame(['total', 'returned', 'limit', 'has_more', 'more', 'profiles'], array_keys($result));
    }

    public function testListProfilesWithLimitZeroReturnsTheCountsOnly()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 0));

        $this->assertSame(3, $result['total']);
        $this->assertSame(0, $result['returned']);
        $this->assertTrue($result['has_more']);
        $this->assertSame([], $result['profiles']);
    }

    public function testListProfilesTreatsANegativeLimitAsZero()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: -1));

        $this->assertSame(0, $result['limit']);
        $this->assertSame(0, $result['returned']);
        $this->assertSame([], $result['profiles']);
    }

    public function testListProfilesCountsTheFilteredTotal()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 1, method: 'POST'));

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['returned']);
        $this->assertFalse($result['has_more']);
    }

    public function testListProfilesCountsTheFilteredTotalWhenPaged()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 1, method: 'GET'));

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['returned']);
        $this->assertTrue($result['has_more']);
    }

    public function testListProfilesWithNoMatchIsNotPaged()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(statusCode: 418));

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['returned']);
        $this->assertFalse($result['has_more']);
        $this->assertSame([], $result['profiles']);
    }

    public function testListProfilesFilterByMethod()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(method: 'POST'));

        $this->assertArrayHasKey('profiles', $result);
        $profiles = $result['profiles'];
        $this->assertCount(1, $profiles);
        $this->assertSame('def456', $profiles[0]['token']);
    }

    public function testListProfilesFilterByStatusCode()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(statusCode: 404));

        $this->assertArrayHasKey('profiles', $result);
        $profiles = $result['profiles'];
        $this->assertCount(1, $profiles);
        $this->assertSame('ghi789', $profiles[0]['token']);
    }

    public function testListProfilesFilterByUrl()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(url: 'users'));

        $this->assertArrayHasKey('profiles', $result);
        $this->assertCount(2, $result['profiles']);
    }

    public function testListProfilesFilterByIp()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(ip: '127.0.0.1'));

        $this->assertArrayHasKey('profiles', $result);
        $this->assertCount(2, $result['profiles']);
    }

    public function testListProfilesFilterByRoute()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(url: '/api/users'));

        $this->assertArrayHasKey('profiles', $result);
        $this->assertCount(2, $result['profiles']);
    }

    public function testGetProfileReturnsProfileWithResourceUri()
    {
        $profile = $this->decodeUntrusted($this->tool->getProfile('abc123'));

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
        $result = $this->decodeUntrusted($this->tool->listProfiles());

        $this->assertArrayHasKey('profiles', $result);
        $profiles = $result['profiles'];
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

    public function testListProfilesReturnsIntegerKeys()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles());

        $this->assertArrayHasKey('profiles', $result);
        $keys = array_keys($result['profiles']);
        $this->assertSame([0, 1, 2], $keys);
    }

    public function testProfilerToolsFailClearlyWhenProfilerSupportIsUnavailable()
    {
        $tool = new ProfilerTool();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Symfony profiler tools are not available in this Mate workspace.');

        $tool->listProfiles();
    }

    /**
     * Decodes a tool response that is expected to carry the untrusted-data
     * envelope, asserts the security notice is present, and returns the payload.
     *
     * @return array<string, mixed>
     */
    private function decodeUntrusted(string $response): array
    {
        $decoded = Toon::decode($response);

        $this->assertIsArray($decoded);
        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);

        return $decoded['untrusted_data'];
    }
}
