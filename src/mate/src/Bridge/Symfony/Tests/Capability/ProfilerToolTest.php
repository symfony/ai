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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Exception\ProfileNotFoundException;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\TestCollector;
use Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\TestCollectorFormatter;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\RuntimeException;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerToolTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryDirs = [];

    private ProfilerTool $tool;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__.'/../Fixtures/profiler';
        $registry = new CollectorRegistry([]);
        $provider = new ProfilerDataProvider($this->fixtureDir, $registry);

        $this->tool = new ProfilerTool($provider);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirs as $dir) {
            $this->removeDirectory($dir);
        }

        $this->temporaryDirs = [];
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

    public function testTriageWithoutArgumentsUsesTheLatestProfile()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage());

        $this->assertSame('newest', $result['token']);
        $this->assertSame('/checkout', $result['url']);
        $this->assertSame('GET', $result['method']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('symfony-profiler://profile/newest', $result['resource_uri']);
    }

    public function testTriageAnswersTheWholeTriageQuestionInOneCall()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage(token: 'newest'));

        $this->assertSame(54, $result['db']['query_count']);
        $this->assertSame(31, $result['db']['duplicate_query_count']);
        $this->assertSame(820.5, $result['time']['duration_ms']);
        $this->assertFalse($result['exception']['has_exception']);
        $this->assertSame(2, $result['logger']['error_count']);
        $this->assertSame(7, $result['logger']['warning_count']);
    }

    public function testTriageReturnsTheMostExpensiveStatementsCappedAndFlagged()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage(token: 'newest'));

        $slowest = $result['db']['slowest_queries'];
        $this->assertCount(5, $slowest);
        $this->assertTrue($result['db']['slowest_queries_truncated']);
        $this->assertSame('SELECT * FROM heavy_0', $slowest[0]['sql']);
        $this->assertSame(12, $slowest[0]['count']);
        // The Doctrine formatter already sorts by total time; triage must not reorder.
        $this->assertEquals([600.0, 500.0, 400.0, 300.0, 200.0], array_column($slowest, 'total_time_ms'));
        // The full grouped list stays behind the collector resource.
        $this->assertArrayNotHasKey('sample_params', $slowest[0]);
    }

    public function testTriageDoesNotFlagTruncationWhenEveryStatementFits()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage(token: 'small'));

        $this->assertCount(2, $result['db']['slowest_queries']);
        $this->assertFalse($result['db']['slowest_queries_truncated']);
    }

    public function testTriageByUrlPicksTheNewestMatchingProfile()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage(url: '/legacy'));

        $this->assertSame('older', $result['token']);
        $this->assertSame('/legacy', $result['url']);
    }

    public function testTriageOmitsCollectorsTheProfileDoesNotHave()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage(token: 'small'));

        $this->assertArrayHasKey('db', $result);
        $this->assertArrayNotHasKey('exception', $result);
        $this->assertArrayNotHasKey('logger', $result);
        $this->assertArrayNotHasKey('time', $result);
    }

    public function testTriageOmitsCollectorsWithoutARegisteredFormatter()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage(token: 'newest'));

        $this->assertArrayNotHasKey('unformatted', $result);
    }

    public function testTriageReportsAPresentException()
    {
        $tool = $this->createTriageTool();

        $result = Toon::decode($tool->triage(token: 'older'));

        $this->assertTrue($result['exception']['has_exception']);
        $this->assertSame('RuntimeException', $result['exception']['class']);
    }

    public function testTriageThrowsExceptionForUnknownToken()
    {
        $tool = $this->createTriageTool();

        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage('Profile not found for token: "nonexistent"');

        $tool->triage(token: 'nonexistent');
    }

    public function testTriageThrowsExceptionWhenNoProfileMatchesTheUrl()
    {
        $tool = $this->createTriageTool();

        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage('No profile found for url "/never-requested"');

        $tool->triage(url: '/never-requested');
    }

    public function testTriageThrowsExceptionWhenProfilerStorageIsEmpty()
    {
        $dir = sys_get_temp_dir().'/mate-profiler-triage-empty-'.bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);
        $this->temporaryDirs[] = $dir;

        $tool = new ProfilerTool(new ProfilerDataProvider($dir, new CollectorRegistry([])));

        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage('No profiles recorded; the profiler storage is empty.');

        $tool->triage();
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

    /**
     * Builds a tool over three profiles: "newest" (/checkout, full set of collectors),
     * "older" (/legacy, carrying an exception) and "small" (db only, few statements).
     */
    private function createTriageTool(): ProfilerTool
    {
        $dir = sys_get_temp_dir().'/mate-profiler-triage-'.bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);
        $this->temporaryDirs[] = $dir;

        $now = time();
        $storage = new FileProfilerStorage('file:'.$dir);

        $heavyQueries = [];
        foreach ([600.0, 500.0, 400.0, 300.0, 200.0, 100.0, 50.0] as $index => $totalTimeMs) {
            $heavyQueries[] = [
                'sql' => \sprintf('SELECT * FROM heavy_%d', $index),
                'count' => 12 - $index,
                'total_time_ms' => $totalTimeMs,
                'avg_time_ms' => round($totalTimeMs / (12 - $index), 2),
                'sample_params' => ['id' => 1],
            ];
        }

        $newest = $this->createTriageProfile('newest', '/checkout', $now, [
            new TestCollector('db', ['query_count' => 54, 'queries' => $heavyQueries], [
                'query_count' => 54,
                'total_time_ms' => 2150.0,
                'duplicate_query_count' => 31,
            ]),
            new TestCollector('time', ['events' => []], ['duration_ms' => 820.5, 'init_time_ms' => 12.25]),
            new TestCollector('exception', [], ['has_exception' => false]),
            new TestCollector('logger', ['logs' => []], [
                'error_count' => 2,
                'warning_count' => 7,
                'deprecation_count' => 1,
            ]),
            new TestCollector('unformatted', ['irrelevant' => true]),
        ]);

        $older = $this->createTriageProfile('older', '/legacy', $now - 60, [
            new TestCollector('exception', [], [
                'has_exception' => true,
                'class' => 'RuntimeException',
                'message' => 'Boom',
            ]),
        ]);

        $small = $this->createTriageProfile('small', '/health', $now - 120, [
            new TestCollector('db', ['query_count' => 2, 'queries' => [
                ['sql' => 'SELECT 1', 'count' => 1, 'total_time_ms' => 2.0, 'avg_time_ms' => 2.0],
                ['sql' => 'SELECT 2', 'count' => 1, 'total_time_ms' => 1.0, 'avg_time_ms' => 1.0],
            ]], ['query_count' => 2, 'total_time_ms' => 3.0, 'duplicate_query_count' => 0]),
        ]);

        foreach ([$newest, $older, $small] as $profile) {
            $this->assertTrue($storage->write($profile));
        }

        clearstatcache();

        $registry = new CollectorRegistry([
            new TestCollectorFormatter('db'),
            new TestCollectorFormatter('time'),
            new TestCollectorFormatter('exception'),
            new TestCollectorFormatter('logger'),
        ]);

        return new ProfilerTool(new ProfilerDataProvider($dir, $registry));
    }

    /**
     * @param list<TestCollector> $collectors
     */
    private function createTriageProfile(string $token, string $url, int $time, array $collectors): Profile
    {
        $profile = new Profile($token);
        $profile->setIp('127.0.0.1');
        $profile->setMethod('GET');
        $profile->setUrl($url);
        $profile->setStatusCode(200);
        $profile->setTime($time);
        $profile->setCollectors($collectors);

        return $profile;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
