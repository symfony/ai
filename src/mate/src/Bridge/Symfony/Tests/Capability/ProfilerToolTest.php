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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Exception\InvalidCollectorException;
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
    private ProfilerTool $tool;
    private string $fixtureDir;

    /**
     * @var list<string>
     */
    private array $temporaryDirs = [];

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

    public function testCompareReportsImprovementWhenQueryCountDrops()
    {
        $tool = $this->createComparableTool(
            ['query_count' => 42, 'total_time_ms' => 120.5, 'duplicate_query_count' => 7],
            ['query_count' => 3, 'total_time_ms' => 18.25, 'duplicate_query_count' => 0],
        );

        $result = Toon::decode($tool->compare('baseline', 'current'));

        $this->assertSame('db', $result['collector']);
        $this->assertSame('improved', $result['verdict']);
        $this->assertSame('baseline', $result['baseline']['token']);
        $this->assertSame(42, $result['baseline']['query_count']);
        $this->assertSame('current', $result['current']['token']);
        $this->assertSame(3, $result['current']['query_count']);
        $this->assertSame(-39, $result['delta']['query_count']);
        $this->assertSame(-102.25, $result['delta']['total_time_ms']);
        $this->assertSame(-7, $result['delta']['duplicate_query_count']);
    }

    public function testCompareReportsUnchangedForIdenticalSummaries()
    {
        $summary = ['query_count' => 12, 'total_time_ms' => 33.0, 'duplicate_query_count' => 2];
        $tool = $this->createComparableTool($summary, $summary);

        $result = Toon::decode($tool->compare('baseline', 'current'));

        $this->assertSame('unchanged', $result['verdict']);
        foreach ($result['delta'] as $value) {
            $this->assertEquals(0, $value);
        }
    }

    public function testCompareReportsRegressionWhenQueryCountRises()
    {
        $tool = $this->createComparableTool(
            ['query_count' => 4],
            ['query_count' => 61],
        );

        $result = Toon::decode($tool->compare('baseline', 'current'));

        $this->assertSame('regressed', $result['verdict']);
        $this->assertSame(57, $result['delta']['query_count']);
    }

    public function testCompareReportsNonNumericFieldsAsChangedInsteadOfDelta()
    {
        $tool = $this->createComparableTool(
            ['query_count' => 8, 'connection' => 'default', 'truncated' => false],
            ['query_count' => 8, 'connection' => 'replica', 'truncated' => true],
        );

        $result = Toon::decode($tool->compare('baseline', 'current'));

        $this->assertSame(['query_count'], array_keys($result['delta']));
        $this->assertSame('replica', $result['current']['connection']);
        $this->assertSame(['baseline' => 'default', 'current' => 'replica'], $result['changed']['connection']);
        $this->assertSame(['baseline' => false, 'current' => true], $result['changed']['truncated']);
    }

    public function testCompareUsesTimesDurationAsLeadingMetric()
    {
        $tool = $this->createComparableTool(
            ['duration_ms' => 250.0],
            ['duration_ms' => 100.0],
            'time',
        );

        $result = Toon::decode($tool->compare('baseline', 'current', 'time'));

        $this->assertSame('time', $result['collector']);
        $this->assertSame('improved', $result['verdict']);
        $this->assertEquals(-150.0, $result['delta']['duration_ms']);
    }

    /**
     * A collector with no field anyone can call unambiguously better when lower (sending
     * more emails is not inherently a regression) must never guess a direction, even
     * though a numeric field visibly changed.
     */
    public function testCompareReportsUnchangedWhenCollectorHasNoDefinedLeadingMetric()
    {
        $tool = $this->createComparableTool(
            ['message_count' => 1],
            ['message_count' => 5],
            'mailer',
        );

        $result = Toon::decode($tool->compare('baseline', 'current', 'mailer'));

        $this->assertSame('unchanged', $result['verdict']);
        $this->assertSame(4, $result['delta']['message_count']);
    }

    /**
     * The bug this fix closes: a collector whose only meaningful signal is a boolean
     * (an exception appearing) used to be silently dropped before the verdict was built,
     * so a baseline without an exception and a current profile with one reported
     * "unchanged" instead of the regression it actually is.
     */
    public function testCompareDetectsANewlyIntroducedExceptionAsRegressed()
    {
        $tool = $this->createComparableTool(
            ['has_exception' => false],
            ['has_exception' => true, 'message' => 'Boom', 'class' => \RuntimeException::class],
            'exception',
        );

        $result = Toon::decode($tool->compare('baseline', 'current', 'exception'));

        $this->assertSame('regressed', $result['verdict']);
        $this->assertSame(['baseline' => false, 'current' => true], $result['changed']['has_exception']);
        $this->assertArrayNotHasKey('has_exception', $result['delta']);
    }

    public function testCompareDetectsAFixedExceptionAsImproved()
    {
        $tool = $this->createComparableTool(
            ['has_exception' => true, 'message' => 'Boom', 'class' => \RuntimeException::class],
            ['has_exception' => false],
            'exception',
        );

        $result = Toon::decode($tool->compare('baseline', 'current', 'exception'));

        $this->assertSame('improved', $result['verdict']);
    }

    public function testCompareAveragesSeveralRunsPerSide()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['query_count' => 40], ['query_count' => 44], ['query_count' => 42]],
            [['query_count' => 4], ['query_count' => 6], ['query_count' => 5]],
        );

        $result = Toon::decode($tool->compare($baseline, $current));

        $this->assertSame(['baseline1', 'baseline2', 'baseline3'], $result['baseline']['tokens']);
        $this->assertSame(3, $result['baseline']['run_count']);
        $this->assertArrayNotHasKey('token', $result['baseline']);
        $this->assertEquals(42.0, $result['baseline']['query_count']);
        $this->assertEquals(5.0, $result['current']['query_count']);
        $this->assertSame('improved', $result['verdict']);
        $this->assertEquals(-37.0, $result['delta']['query_count']);
    }

    public function testCompareWithASingleRunPerSideKeepsTheSingularTokenShape()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['query_count' => 40]],
            [['query_count' => 4]],
        );

        $result = Toon::decode($tool->compare($baseline, $current));

        $this->assertSame('baseline1', $result['baseline']['token']);
        $this->assertArrayNotHasKey('tokens', $result['baseline']);
        $this->assertArrayNotHasKey('run_count', $result['baseline']);
    }

    public function testCompareAveragesABooleanFieldIntoAFlakinessRate()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['has_exception' => false], ['has_exception' => false]],
            [['has_exception' => true], ['has_exception' => false]],
            'exception',
        );

        $result = Toon::decode($tool->compare($baseline, $current, 'exception'));

        $this->assertEquals(0.0, $result['baseline']['has_exception']);
        $this->assertEquals(0.5, $result['current']['has_exception']);
        $this->assertEquals(0.5, $result['delta']['has_exception']);
    }

    public function testCompareDropsANonNumericFieldTheRunsDisagreeOn()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['query_count' => 1, 'connection' => 'default'], ['query_count' => 1, 'connection' => 'replica']],
            [['query_count' => 1, 'connection' => 'default'], ['query_count' => 1, 'connection' => 'default']],
        );

        $result = Toon::decode($tool->compare($baseline, $current));

        $this->assertArrayNotHasKey('connection', $result['baseline']);
        $this->assertSame('default', $result['current']['connection']);
    }

    public function testCompareThrowsExceptionForBlankTokenList()
    {
        $tool = $this->createComparableTool(['query_count' => 1], ['query_count' => 1]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one profiler token is required.');

        $tool->compare(' , ', 'current');
    }

    public function testCompareThrowsExceptionForUnknownToken()
    {
        $tool = $this->createComparableTool(['query_count' => 1], ['query_count' => 1]);

        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessage('Profile not found for token: "nonexistent"');

        $tool->compare('baseline', 'nonexistent');
    }

    public function testCompareThrowsExceptionForUnknownCollector()
    {
        $tool = $this->createComparableTool(['query_count' => 1], ['query_count' => 1]);

        $this->expectException(InvalidCollectorException::class);

        $tool->compare('baseline', 'current', 'unknown');
    }

    public function testCompareThrowsExceptionForCollectorWithoutSummary()
    {
        $tool = $this->createComparableTool(['query_count' => 1], ['query_count' => 1]);

        $this->expectException(InvalidCollectorException::class);
        $this->expectExceptionMessage('Collector "unformatted" of profile "baseline" does not provide a summary to compare');

        $tool->compare('baseline', 'current', 'unformatted');
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

    /**
     * Builds a tool over two profiles named "baseline" and "current", each carrying the given
     * summary in the given collector.
     *
     * @param array<string, mixed> $baselineSummary
     * @param array<string, mixed> $currentSummary
     */
    private function createComparableTool(array $baselineSummary, array $currentSummary, string $collector = 'db'): ProfilerTool
    {
        $dir = sys_get_temp_dir().'/mate-profiler-compare-'.bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);
        $this->temporaryDirs[] = $dir;

        $storage = new FileProfilerStorage('file:'.$dir);
        $this->assertTrue($storage->write($this->createProfile('baseline', $collector, $baselineSummary)));
        $this->assertTrue($storage->write($this->createProfile('current', $collector, $currentSummary)));
        clearstatcache();

        $registry = new CollectorRegistry([new TestCollectorFormatter($collector)]);

        return new ProfilerTool(new ProfilerDataProvider($dir, $registry));
    }

    /**
     * Builds a tool over a set of "baseline1", "baseline2", ... and "current1", "current2", ...
     * profiles, one per given summary, all carrying the given collector.
     *
     * @param list<array<string, mixed>> $baselineSummaries
     * @param list<array<string, mixed>> $currentSummaries
     *
     * @return array{0: ProfilerTool, 1: string, 2: string} the tool plus the comma-joined baseline and current token lists
     */
    private function createComparableToolWithRuns(array $baselineSummaries, array $currentSummaries, string $collector = 'db'): array
    {
        $dir = sys_get_temp_dir().'/mate-profiler-compare-'.bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);
        $this->temporaryDirs[] = $dir;

        $storage = new FileProfilerStorage('file:'.$dir);
        $baselineTokens = [];
        foreach ($baselineSummaries as $index => $summary) {
            $token = 'baseline'.($index + 1);
            $baselineTokens[] = $token;
            $this->assertTrue($storage->write($this->createProfile($token, $collector, $summary)));
        }

        $currentTokens = [];
        foreach ($currentSummaries as $index => $summary) {
            $token = 'current'.($index + 1);
            $currentTokens[] = $token;
            $this->assertTrue($storage->write($this->createProfile($token, $collector, $summary)));
        }

        clearstatcache();

        $registry = new CollectorRegistry([new TestCollectorFormatter($collector)]);

        return [new ProfilerTool(new ProfilerDataProvider($dir, $registry)), implode(',', $baselineTokens), implode(',', $currentTokens)];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function createProfile(string $token, string $collector, array $summary): Profile
    {
        $profile = new Profile($token);
        $profile->setIp('127.0.0.1');
        $profile->setMethod('GET');
        $profile->setUrl('/api/users');
        $profile->setStatusCode(200);
        // FileProfilerStorage::write() randomly prunes profiles older than two days.
        $profile->setTime(time());
        $profile->setCollectors([
            new TestCollector($collector, $summary),
            new TestCollector('unformatted', ['irrelevant' => true]),
        ]);

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
