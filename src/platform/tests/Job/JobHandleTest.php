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

        $this->assertSame(600, $handle->withProvider('minimax')->getMaxDuration());
        $this->assertSame(600, $handle->withData(['file_id' => '1'])->getMaxDuration());
    }

    public function testItRejectsANonsensicalDuration()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one second');

        new JobHandle('task-1', [], null, 0);
    }

    public function testItIsNotBoundToAProviderUntilStamped()
    {
        $handle = new JobHandle('task-1');

        $this->assertNull($handle->getProvider());
        $this->assertSame('minimax', $handle->withProvider('minimax')->getProvider());

        // the original stays untouched
        $this->assertNull($handle->getProvider());
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
    }
}
