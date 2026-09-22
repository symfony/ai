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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Job\JobHandle;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobHandleTest extends TestCase
{
    public function testItSurvivesASerializationRoundTrip()
    {
        $handle = new JobHandle('task-1', ['query_path' => 'query/video_generation', 'mime_type' => 'video/mp4'], 'minimax', 600);

        $restored = JobHandle::fromArray(json_decode(json_encode($handle), true, flags: \JSON_THROW_ON_ERROR));

        $this->assertSame('task-1', $restored->getId());
        $this->assertSame('minimax', $restored->getProvider());
        $this->assertSame('query/video_generation', $restored->get('query_path'));
        $this->assertSame('video/mp4', $restored->get('mime_type'));

        // Without this a job picked up in a worker would be waited for with the wrong budget.
        $this->assertSame(600, $restored->getMaxDuration());
    }

    public function testCopiesKeepTheExpectedDuration()
    {
        $handle = new JobHandle('task-1', [], null, 600);

        $this->assertSame(600, $handle->withData(['file_id' => '1'])->getMaxDuration());
    }

    public function testItRejectsANonsensicalDuration()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one second');

        new JobHandle('task-1', [], null, 0);
    }

    public function testItCarriesThePollIntervalThroughASerializationRoundTrip()
    {
        $handle = new JobHandle('task-1', [], 'minimax', 600, 5.0);

        $restored = JobHandle::fromArray(json_decode(json_encode($handle), true, flags: \JSON_THROW_ON_ERROR));

        $this->assertSame(5.0, $restored->getPollInterval());
    }

    // A whole-number interval comes back from JSON as an int, which must not make a stored handle unreadable.
    public function testItAcceptsAWholeNumberPollIntervalFromStorage()
    {
        $this->assertSame(5.0, JobHandle::fromArray(['id' => 'task-1', 'poll_interval' => 5])->getPollInterval());
    }

    public function testAHandleStatesNoPollIntervalByDefault()
    {
        $this->assertNull((new JobHandle('task-1'))->getPollInterval());
    }

    public function testCopiesKeepThePollInterval()
    {
        $handle = new JobHandle('task-1', [], null, 600, 5.0);

        $this->assertSame(5.0, $handle->withData(['file_id' => '1'])->getPollInterval());
    }

    public function testItRejectsANonsensicalPollInterval()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        new JobHandle('task-1', [], null, null, 0.0);
    }

    public function testItCarriesTheProviderItWasCreatedFor()
    {
        $this->assertNull((new JobHandle('task-1'))->getProvider());
        $this->assertSame('minimax', (new JobHandle('task-1', [], 'minimax'))->withData(['file_id' => '1'])->getProvider());
    }

    public function testWithDataMergesIntoTheExistingData()
    {
        $handle = (new JobHandle('task-1', ['mime_type' => 'video/mp4']))->withData(['file_id' => '42']);

        $this->assertSame('video/mp4', $handle->get('mime_type'));
        $this->assertSame('42', $handle->get('file_id'));
    }

    public function testGetFallsBackToTheGivenDefault()
    {
        $this->assertSame('fallback', (new JobHandle('task-1'))->get('missing', 'fallback'));
        $this->assertNull((new JobHandle('task-1'))->get('missing'));
    }

    public function testItRejectsAnEmptyIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty job identifier');

        new JobHandle('');
    }

    public function testItSurvivesAStringRoundTrip()
    {
        $handle = new JobHandle('task-1', ['mime_type' => 'video/mp4'], 'minimax', 600, 2.5);

        $this->assertEquals($handle, JobHandle::fromString($handle->toString()));
    }

    public function testItRejectsAStringThatIsNotJson()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be valid JSON');

        JobHandle::fromString('not json');
    }

    public function testItRejectsAStringThatIsNotAnArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must decode to an array');

        JobHandle::fromString('"task-1"');
    }

    /**
     * @param array<string, mixed> $handle
     */
    #[DataProvider('provideInvalidSerializedHandles')]
    public function testItRejectsBrokenSerializedHandles(array $handle, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        JobHandle::fromArray($handle);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidSerializedHandles(): iterable
    {
        yield 'no id' => [['data' => []], 'non-empty "id" key'];
        yield 'empty id' => [['id' => ''], 'non-empty "id" key'];
        yield 'id not a string' => [['id' => 42], 'non-empty "id" key'];
        yield 'provider not a string' => [['id' => 'task-1', 'provider' => 42], '"provider" key'];
        yield 'data not an array' => [['id' => 'task-1', 'data' => 'nope'], '"data" key'];
        yield 'max duration not an int' => [['id' => 'task-1', 'max_duration' => '600'], '"max_duration" key'];
        yield 'poll interval not a number' => [['id' => 'task-1', 'poll_interval' => '5'], '"poll_interval" key'];
        yield 'poll interval not positive' => [['id' => 'task-1', 'poll_interval' => 0.0], 'greater than zero'];
    }
}
