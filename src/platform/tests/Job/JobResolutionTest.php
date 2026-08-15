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
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\UnexpectedResultTypeException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Test\MockModelCatalog;
use Symfony\AI\Platform\Test\MockModelClient;
use Symfony\AI\Platform\Test\MockResultConverter;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * The round trip that makes a job a job: start it, put the handle away, and resolve it later through
 * the platform - without the object that started it.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobResolutionTest extends TestCase
{
    public function testAHandleIsStampedWithTheProviderItCameFrom()
    {
        $handle = $this->platform('renamed-provider')->invoke('async-model', 'go')->asJob();

        $this->assertSame('task-1', $handle->getId());
        $this->assertSame('renamed-provider', $handle->getProvider(), 'the converter cannot know the name, the provider stamps it');
    }

    public function testAStoredHandleResolvesThroughThePlatformInAnotherProcess()
    {
        // Process one: start the job, keep nothing but the serialized handle.
        $stored = json_encode($this->platform()->invoke('async-model', 'go')->asJob(), \JSON_THROW_ON_ERROR);

        // Process two: only the string survived, the platform is built from scratch.
        $handle = JobHandle::fromArray(json_decode($stored, true, flags: \JSON_THROW_ON_ERROR));
        $jobClient = $this->platform()->getJobClient($handle);

        $this->assertTrue($jobClient->getStatus($handle)->is(JobStateCase::SUCCEEDED));
        $this->assertSame('FAKE_VIDEO', $jobClient->getResult($handle)->getContent());
    }

    public function testAnUnboundHandleCannotBeResolved()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not bound to a provider');

        $this->platform()->getJobClient(new JobHandle('task-1'));
    }

    public function testAHandleNamingAnUnknownProviderCannotBeResolved()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No provider named "elsewhere"');

        $this->platform()->getJobClient((new JobHandle('task-1'))->withProvider('elsewhere'));
    }

    public function testAProviderWithoutJobsSaysSoInsteadOfReturningNull()
    {
        $platform = new Platform([$this->provider('sync-only', $this->jobConverter(), null)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not run asynchronous jobs');

        $platform->getJobClient((new JobHandle('task-1'))->withProvider('sync-only'));
    }

    public function testAskingForAJobOnASynchronousResultFails()
    {
        $platform = new Platform([$this->provider('sync-only', new MockResultConverter(), null)]);

        $this->expectException(UnexpectedResultTypeException::class);

        $platform->invoke('async-model', 'go')->asJob();
    }

    /**
     * Reaching for the payload on a provider that answers asynchronously is the mistake this API
     * invites, so the error has to point at the way out rather than only name two class names.
     */
    public function testReachingForThePayloadOfAJobSaysWhatToDoInstead()
    {
        $this->expectException(UnexpectedResultTypeException::class);
        $this->expectExceptionMessage('The provider started a job instead of answering directly: read the handle with asJob()');

        $this->platform()->invoke('async-model', 'go')->asBinary();
    }

    private function platform(string $name = 'jobs'): Platform
    {
        return new Platform([$this->provider($name, $this->jobConverter(), $this->jobClient())]);
    }

    private function provider(string $name, ResultConverterInterface $converter, ?JobClientInterface $jobClient): Provider
    {
        return new Provider(
            $name,
            [new MockModelClient('accepted')],
            [$converter],
            new MockModelCatalog(['async-model' => ['class' => Model::class, 'capabilities' => [Capability::INPUT_TEXT]]]),
            null,
            null,
            $jobClient,
        );
    }

    /**
     * Stands in for a bridge converter whose provider answered with a task identifier instead of a
     * payload - notably without knowing the name its provider was registered under.
     */
    private function jobConverter(): ResultConverterInterface
    {
        return new class implements ResultConverterInterface {
            public function supports(Model $model): bool
            {
                return true;
            }

            public function convert(RawResultInterface $result, array $options = []): ResultInterface
            {
                return new JobResult(new JobHandle('task-1', ['mime_type' => 'video/mp4']));
            }

            public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
            {
                return null;
            }
        };
    }

    private function jobClient(): JobClientInterface
    {
        return new class implements JobClientInterface {
            public function supports(JobHandle $handle): bool
            {
                return true;
            }

            public function getStatus(JobHandle $handle): JobStatus
            {
                return new JobStatus(JobStateCase::SUCCEEDED, 'Success');
            }

            public function getResult(JobHandle $handle): ResultInterface
            {
                return new BinaryResult('FAKE_VIDEO', $handle->get('mime_type'));
            }
        };
    }
}
