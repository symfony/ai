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
use Symfony\AI\Mate\Exception\InvalidArgumentException;
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

    public function testListProfilesSaysWhenItReturnedOnlyAPage()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 2));

        $this->assertSame(3, $result['total']);
        $this->assertTrue($result['truncated']);
        $this->assertCount(2, $result['profiles']);
    }

    public function testListProfilesSaysWhenItReturnedEverything()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles());

        $this->assertSame(3, $result['total']);
        $this->assertFalse($result['truncated']);
        $this->assertCount(3, $result['profiles']);
    }

    public function testListProfilesWithLimitEqualToTotalIsNotTruncated()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 3));

        $this->assertFalse($result['truncated']);
        $this->assertCount(3, $result['profiles']);
    }

    public function testListProfilesPutsTheCountsBeforeTheProfiles()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 1));

        $this->assertSame(['total', 'truncated', 'profiles'], array_keys($result));
    }

    public function testListProfilesWithLimitZeroReturnsEveryProfile()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 0));

        $this->assertSame(3, $result['total']);
        $this->assertFalse($result['truncated']);
        $this->assertCount(3, $result['profiles']);
    }

    public function testListProfilesRejectsANegativeLimit()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be 0 or greater.');

        $this->tool->listProfiles(limit: -1);
    }

    public function testListProfilesCountsTheFilteredTotal()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 1, method: 'POST'));

        $this->assertSame(1, $result['total']);
        $this->assertFalse($result['truncated']);
        $this->assertCount(1, $result['profiles']);
    }

    public function testListProfilesCountsTheFilteredTotalWhenTruncated()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(limit: 1, method: 'GET'));

        $this->assertSame(2, $result['total']);
        $this->assertTrue($result['truncated']);
        $this->assertCount(1, $result['profiles']);
    }

    public function testListProfilesWithNoMatchIsNotTruncated()
    {
        $result = $this->decodeUntrusted($this->tool->listProfiles(statusCode: 418));

        $this->assertSame(0, $result['total']);
        $this->assertFalse($result['truncated']);
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

    public function testCompareReportsImprovementWhenQueryCountDrops()
    {
        $tool = $this->createComparableTool(
            ['query_count' => 42, 'total_time_ms' => 120.5, 'duplicate_query_count' => 7],
            ['query_count' => 3, 'total_time_ms' => 18.25, 'duplicate_query_count' => 0],
        );

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current'));

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

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current'));

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

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current'));

        $this->assertSame('regressed', $result['verdict']);
        $this->assertSame(57, $result['delta']['query_count']);
    }

    public function testCompareReportsNonNumericFieldsAsChangedInsteadOfDelta()
    {
        $tool = $this->createComparableTool(
            ['query_count' => 8, 'connection' => 'default', 'truncated' => false],
            ['query_count' => 8, 'connection' => 'replica', 'truncated' => true],
        );

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current'));

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

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current', 'time'));

        $this->assertSame('time', $result['collector']);
        $this->assertSame('improved', $result['verdict']);
        $this->assertEquals(-150.0, $result['delta']['duration_ms']);
    }

    /**
     * A collector with no field anyone can call unambiguously better when lower (sending
     * more emails is not inherently a regression) must not claim "unchanged" while a
     * numeric field visibly changed.
     */
    public function testCompareOmitsVerdictWhenCollectorHasNoDefinedLeadingMetric()
    {
        $tool = $this->createComparableTool(
            ['message_count' => 1],
            ['message_count' => 5],
            'mailer',
        );

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current', 'mailer'));

        $this->assertArrayNotHasKey('verdict', $result);
        $this->assertSame(4, $result['delta']['message_count']);
    }

    public function testCompareTreatsSmallTimeJitterAsUnchanged()
    {
        $tool = $this->createComparableTool(['duration_ms' => 120.41], ['duration_ms' => 120.43], 'time');

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current', 'time'));

        $this->assertSame('unchanged', $result['verdict']);
    }

    public function testCompareStillFlagsALargeMemoryIncreaseAsRegressed()
    {
        $tool = $this->createComparableTool(['memory_mb' => 10.0], ['memory_mb' => 12.0], 'memory');

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current', 'memory'));

        $this->assertSame('regressed', $result['verdict']);
    }

    public function testCompareGivesADeltaWhenOneSideIsASingleBoolRunAndTheOtherAnAverage()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns([['has_exception' => false]], [['has_exception' => true], ['has_exception' => false]], 'exception');

        $result = $this->decodeUntrusted($tool->compare($baseline, $current, 'exception'));

        $this->assertEquals(0.5, $result['delta']['has_exception']);
        $this->assertArrayNotHasKey('has_exception', $result['changed']);
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

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current', 'exception'));

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

        $result = $this->decodeUntrusted($tool->compare('baseline', 'current', 'exception'));

        $this->assertSame('improved', $result['verdict']);
    }

    public function testCompareAveragesSeveralRunsPerSide()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['query_count' => 40], ['query_count' => 44], ['query_count' => 42]],
            [['query_count' => 4], ['query_count' => 6], ['query_count' => 5]],
        );

        $result = $this->decodeUntrusted($tool->compare($baseline, $current));

        $this->assertSame(['baseline1', 'baseline2', 'baseline3'], $result['baseline']['tokens']);
        $this->assertSame(3, $result['baseline']['run_count']);
        $this->assertArrayNotHasKey('token', $result['baseline']);
        $this->assertEquals(42.0, $result['baseline']['query_count']);
        $this->assertEquals(5.0, $result['current']['query_count']);
        $this->assertSame('improved', $result['verdict']);
        $this->assertEquals(-37.0, $result['delta']['query_count']);
    }

    public function testCompareUsesTheMedianSoOneSlowRunDoesNotDecideTheResult()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['duration_ms' => 800.0], ['duration_ms' => 805.0], ['duration_ms' => 795.0]],
            [['duration_ms' => 500.0], ['duration_ms' => 505.0], ['duration_ms' => 1500.0]],
            'time',
        );

        $result = $this->decodeUntrusted($tool->compare($baseline, $current, 'time'));

        $this->assertEquals(505.0, $result['current']['duration_ms']);
        $this->assertEquals(-295.0, $result['delta']['duration_ms']);
    }

    public function testCompareListsAnOutlierRunAndMakesTheVerdictInconclusive()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['duration_ms' => 800.0], ['duration_ms' => 805.0], ['duration_ms' => 795.0]],
            [['duration_ms' => 500.0], ['duration_ms' => 505.0], ['duration_ms' => 1500.0]],
            'time',
        );

        $result = $this->decodeUntrusted($tool->compare($baseline, $current, 'time'));

        $this->assertSame('inconclusive', $result['verdict']);
        $this->assertArrayNotHasKey('baseline', $result['outliers']);
        $this->assertEquals(1500.0, $result['outliers']['current']['duration_ms']['current3']);
        $this->assertCount(3, $result['outliers']['current']['duration_ms']);
        $this->assertStringContainsString('duration_ms', $result['note']);
    }

    public function testCompareKeepsTheVerdictWhenOnlyANonLeadingFieldHasAnOutlier()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['duration_ms' => 800.0, 'init_time_ms' => 10.0], ['duration_ms' => 802.0, 'init_time_ms' => 10.0], ['duration_ms' => 798.0, 'init_time_ms' => 10.0]],
            [['duration_ms' => 500.0, 'init_time_ms' => 10.0], ['duration_ms' => 502.0, 'init_time_ms' => 10.0], ['duration_ms' => 498.0, 'init_time_ms' => 1000.0]],
            'time',
        );

        $result = $this->decodeUntrusted($tool->compare($baseline, $current, 'time'));

        $this->assertSame('improved', $result['verdict']);
        $this->assertArrayHasKey('init_time_ms', $result['outliers']['current']);
        $this->assertArrayNotHasKey('note', $result);
    }

    public function testCompareReportsNoOutliersWhenTheRunsAgree()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['query_count' => 40], ['query_count' => 44], ['query_count' => 42]],
            [['query_count' => 4], ['query_count' => 6], ['query_count' => 5]],
        );

        $result = $this->decodeUntrusted($tool->compare($baseline, $current));

        $this->assertArrayNotHasKey('outliers', $result);
        $this->assertSame('improved', $result['verdict']);
    }

    public function testCompareWithASingleRunPerSideKeepsTheSingularTokenShape()
    {
        [$tool, $baseline, $current] = $this->createComparableToolWithRuns(
            [['query_count' => 40]],
            [['query_count' => 4]],
        );

        $result = $this->decodeUntrusted($tool->compare($baseline, $current));

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

        $result = $this->decodeUntrusted($tool->compare($baseline, $current, 'exception'));

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

        $result = $this->decodeUntrusted($tool->compare($baseline, $current));

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

    public function testTriageWithoutArgumentsUsesTheLatestProfile()
    {
        $tool = $this->createTriageTool();

        $result = $this->decodeUntrusted($tool->triage());

        $this->assertSame('newest', $result['token']);
        $this->assertSame('/checkout', $result['url']);
        $this->assertSame('GET', $result['method']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('symfony-profiler://profile/newest', $result['resource_uri']);
    }

    public function testTriageAnswersTheWholeTriageQuestionInOneCall()
    {
        $tool = $this->createTriageTool();

        $result = $this->decodeUntrusted($tool->triage(token: 'newest'));

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

        $result = $this->decodeUntrusted($tool->triage(token: 'newest'));

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

        $result = $this->decodeUntrusted($tool->triage(token: 'small'));

        $this->assertCount(2, $result['db']['slowest_queries']);
        $this->assertFalse($result['db']['slowest_queries_truncated']);
    }

    public function testTriageByUrlPicksTheNewestMatchingProfile()
    {
        $tool = $this->createTriageTool();

        $result = $this->decodeUntrusted($tool->triage(url: '/legacy'));

        $this->assertSame('older', $result['token']);
        $this->assertSame('/legacy', $result['url']);
    }

    public function testTriageOmitsCollectorsTheProfileDoesNotHave()
    {
        $tool = $this->createTriageTool();

        $result = $this->decodeUntrusted($tool->triage(token: 'small'));

        $this->assertArrayHasKey('db', $result);
        $this->assertArrayNotHasKey('exception', $result);
        $this->assertArrayNotHasKey('logger', $result);
        $this->assertArrayNotHasKey('time', $result);
    }

    public function testTriageOmitsCollectorsWithoutARegisteredFormatter()
    {
        $tool = $this->createTriageTool();

        $result = $this->decodeUntrusted($tool->triage(token: 'newest'));

        $this->assertArrayNotHasKey('unformatted', $result);
    }

    public function testTriageReportsAPresentException()
    {
        $tool = $this->createTriageTool();

        $result = $this->decodeUntrusted($tool->triage(token: 'older'));

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
