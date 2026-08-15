<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Job;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Tests\Fixtures\Job\SingleProviderJobPlatform;
use Symfony\AI\Platform\TraceablePlatform;

/**
 * A job handle is only useful if it can be resolved through the very platform it came from - and in
 * a Symfony application that platform is wrapped: `TraceablePlatform` whenever the profiler is on.
 * A decorator that silently drops `getJobClient()` turns the documented usage into a fatal error in
 * dev while it keeps working in prod, so the forwarding is pinned down here. The cache and failover
 * bridges carry the same test in their own suites.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobPlatformDecoratorTest extends TestCase
{
    public function testTracingForwardsToTheTracedPlatform()
    {
        $handle = (new JobHandle('task-1'))->withProvider('minimax');
        $platform = new TraceablePlatform(new SingleProviderJobPlatform('minimax'));

        $this->assertTrue($platform->getJobClient($handle)->getStatus($handle)->is(JobStateCase::SUCCEEDED));
    }

    public function testTracingSaysSoWhenTheTracedPlatformHasNoJobs()
    {
        $platform = new TraceablePlatform($this->createStub(PlatformInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not run asynchronous jobs');

        $platform->getJobClient((new JobHandle('task-1'))->withProvider('minimax'));
    }
}
