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
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerSummaryToolTest extends TestCase
{
    private string $dir;
    private int $time;

    protected function setUp(): void
    {
        // recent, because the storage purges profiles older than its lifetime when writing
        $this->time = time() - 1000;
        $this->dir = sys_get_temp_dir().'/mate-profiler-summary-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testSummarizesRequestsAndGroupsFailuresByCount()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        $this->write($storage, 'GET', '/ok', 200);
        $this->write($storage, 'GET', '/missing?page=2', 404);
        $this->write($storage, 'POST', '/checkout?token=secret', 500);
        $this->write($storage, 'POST', '/checkout', 500);
        $this->write($storage, 'POST', '/checkout?token=other', 500);
        $this->write($storage, 'GET', '/missing?page=3', 404);
        $this->write($storage, 'GET', '/redirect', 302);

        $result = $this->summarize();

        $this->assertSame(7, $result['requests']);
        $this->assertSame(5, $result['failed']);
        $this->assertFalse($result['truncated']);
        $this->assertSame(['POST 500 /checkout' => 3, 'GET 404 /missing' => 2], $result['failing_requests_by_count']);
        $this->assertFalse($result['failing_requests_truncated']);
    }

    public function testSubRequestsAreNotCounted()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        $parent = $this->write($storage, 'GET', '/page', 200);
        $this->write($storage, 'GET', '/_fragment', 500, $parent);
        $this->write($storage, 'GET', '/_fragment', 500, $parent);

        $result = $this->summarize();

        $this->assertSame(1, $result['requests']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['failing_requests_by_count'] ?? []);
    }

    public function testLimitCoversTheLatestTopLevelRequestsAndReportsTruncation()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        $this->write($storage, 'GET', '/old', 500);
        $parent = $this->write($storage, 'GET', '/new', 200);
        $this->write($storage, 'GET', '/_fragment', 500, $parent);
        $this->write($storage, 'GET', '/newest', 404);

        $result = $this->summarize(limit: 2);

        $this->assertSame(2, $result['requests']);
        $this->assertSame(1, $result['failed']);
        $this->assertTrue($result['truncated']);
        $this->assertSame(['GET 404 /newest' => 1], $result['failing_requests_by_count']);
    }

    public function testLimitEqualToTheNumberOfRequestsIsNotTruncated()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        $this->write($storage, 'GET', '/a', 200);
        $this->write($storage, 'GET', '/b', 500);

        $result = $this->summarize(limit: 2);

        $this->assertSame(2, $result['requests']);
        $this->assertFalse($result['truncated']);
    }

    public function testLimitZeroLooksAtAllRequests()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        for ($i = 0; $i < 5; ++$i) {
            $this->write($storage, 'GET', '/boom', 500);
        }

        $result = $this->summarize(limit: 0);

        $this->assertSame(5, $result['requests']);
        $this->assertSame(5, $result['failed']);
        $this->assertFalse($result['truncated']);
    }

    public function testConsoleCommandProfilesAreNotCountedAsRequests()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        $this->write($storage, 'GET', '/boom', 500);
        $this->write($storage, 'GET', '/app:import', 1, null, 'command');

        $result = $this->summarize();

        $this->assertSame(1, $result['requests']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(['GET 500 /boom' => 1], $result['failing_requests_by_count']);
    }

    public function testFailingRequestsAreCappedAndReportTruncation()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        for ($i = 0; $i < 25; ++$i) {
            $this->write($storage, 'GET', '/boom'.$i, 500);
        }
        $this->write($storage, 'GET', '/boom3', 500);

        $result = $this->summarize();

        $this->assertSame(26, $result['failed']);
        $this->assertTrue($result['failing_requests_truncated']);
        $this->assertCount(20, $result['failing_requests_by_count']);
        $this->assertSame(2, $result['failing_requests_by_count']['GET 500 /boom3']);
    }

    public function testTiesAreOrderedByKey()
    {
        $storage = new FileProfilerStorage('file:'.$this->dir);
        $this->write($storage, 'GET', '/z', 500);
        $this->write($storage, 'GET', '/b', 500);
        $this->write($storage, 'GET', '/a', 500);

        $result = $this->summarize();

        $this->assertSame(['GET 500 /a', 'GET 500 /b', 'GET 500 /z'], array_keys($result['failing_requests_by_count']));
    }

    public function testNegativeLimitIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be 0 or greater.');

        $this->createTool()->summarizeTraffic(limit: -1);
    }

    public function testNoProfilesGivesAnEmptySummary()
    {
        $result = $this->summarize();

        $this->assertSame(0, $result['requests']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse($result['truncated']);
    }

    public function testContextFilterNarrowsTheSummary()
    {
        $web = new FileProfilerStorage('file:'.$this->dir.'/web');
        $api = new FileProfilerStorage('file:'.$this->dir.'/api');
        $this->write($web, 'GET', '/web', 500);
        $this->write($api, 'GET', '/api', 200);
        $this->write($api, 'GET', '/api', 503);

        $tool = new ProfilerTool(new ProfilerDataProvider(['web' => $this->dir.'/web', 'api' => $this->dir.'/api'], new CollectorRegistry([])));

        $all = $this->decode($tool->summarizeTraffic());
        $this->assertSame(3, $all['requests']);
        $this->assertSame(2, $all['failed']);

        $api = $this->decode($tool->summarizeTraffic(context: 'api'));
        $this->assertSame(2, $api['requests']);
        $this->assertSame(['GET 503 /api' => 1], $api['failing_requests_by_count']);
    }

    public function testFailsClearlyWhenProfilerSupportIsUnavailable()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Symfony profiler tools are not available in this Mate workspace.');

        (new ProfilerTool())->summarizeTraffic();
    }

    private function createTool(): ProfilerTool
    {
        return new ProfilerTool(new ProfilerDataProvider($this->dir, new CollectorRegistry([])));
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(int $limit = 500): array
    {
        return $this->decode($this->createTool()->summarizeTraffic($limit));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $response): array
    {
        $decoded = Toon::decode($response);

        $this->assertIsArray($decoded);
        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);

        return $decoded['untrusted_data'];
    }

    private function write(FileProfilerStorage $storage, string $method, string $url, int $status, ?Profile $parent = null, ?string $virtualType = null): Profile
    {
        $profile = new Profile(bin2hex(random_bytes(4)));
        $profile->setMethod($method);
        $profile->setUrl('http://localhost'.$url);
        $profile->setIp('127.0.0.1');
        $profile->setStatusCode($status);
        $profile->setTime($this->time++);
        $profile->setVirtualType($virtualType);
        if (null !== $parent) {
            $profile->setParent($parent);
        }

        $storage->write($profile);

        return $profile;
    }
}
