<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateNormalizer;
use Symfony\Component\Clock\MockClock;

final class WorkflowStateNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $clock = new MockClock('2025-01-01 00:00:00');
        $normalizer = new WorkflowStateNormalizer($clock);

        $state = new WorkflowState('wf-1', ['foo' => 'bar'], ['place_a'], 'place_b', 'to_place_c', ['fork_a', 'fork_b']);

        $result = $normalizer->normalize($state);

        $this->assertSame('wf-1', $result['id']);
        $this->assertSame(['foo' => 'bar'], $result['data']);
        $this->assertArrayNotHasKey('updated_at', $result['data']);
        $this->assertSame(['place_a'], $result['completed_places']);
        $this->assertSame('place_b', $result['current_place']);
        $this->assertSame('to_place_c', $result['next_transition']);
        $this->assertSame(['fork_a', 'fork_b'], $result['interrupted_fork']);
        $this->assertSame($clock->now()->getTimestamp(), $result['updated_at']);
    }

    public function testNormalizeReturnsEmptyForNonWorkflowState()
    {
        $normalizer = new WorkflowStateNormalizer();

        $result = $normalizer->normalize(new \stdClass());

        $this->assertSame([], $result);
    }

    public function testDenormalize()
    {
        $normalizer = new WorkflowStateNormalizer();

        $data = [
            'id' => 'wf-2',
            'data' => ['key' => 'value'],
            'completed_places' => ['step_one'],
            'current_place' => 'step_two',
            'next_transition' => 'to_step_three',
            'interrupted_fork' => ['branch_a', 'branch_b'],
            'updated_at' => 1735689600,
        ];

        $state = $normalizer->denormalize($data, WorkflowStateInterface::class);

        $this->assertSame('wf-2', $state->getId());
        $this->assertSame(['key' => 'value'], $state->all());
        $this->assertSame(['step_one'], $state->getCompletedPlaces());
        $this->assertSame('step_two', $state->getCurrentPlace());
        $this->assertSame('to_step_three', $state->getNextTransition());
        $this->assertSame(['branch_a', 'branch_b'], $state->getInterruptedFork());
        $this->assertSame(1735689600, $state->getUpdatedAt()?->getTimestamp());
    }

    public function testDenormalizeToleratesMissingOptionalKeys()
    {
        $normalizer = new WorkflowStateNormalizer();

        $state = $normalizer->denormalize(['id' => 'wf-3'], WorkflowStateInterface::class);

        $this->assertSame('wf-3', $state->getId());
        $this->assertSame([], $state->all());
        $this->assertSame([], $state->getCompletedPlaces());
        $this->assertNull($state->getCurrentPlace());
        $this->assertNull($state->getNextTransition());
        $this->assertSame([], $state->getInterruptedFork());
        $this->assertNull($state->getUpdatedAt());
    }

    public function testDenormalizeThrowsWhenIdMissing()
    {
        $normalizer = new WorkflowStateNormalizer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot denormalize workflow state: the "id" key is missing.');

        $normalizer->denormalize(['data' => []], WorkflowStateInterface::class);
    }

    public function testNormalizeThenDenormalizeDoesNotLeakMetadata()
    {
        $normalizer = new WorkflowStateNormalizer();
        $state = new WorkflowState('wf-4', ['key' => 'value']);

        $roundTripped = $normalizer->denormalize($normalizer->normalize($state), WorkflowStateInterface::class);

        $this->assertSame(['key' => 'value'], $roundTripped->all());
    }

    public function testSupportsNormalization()
    {
        $normalizer = new WorkflowStateNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new WorkflowState('id')));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    public function testSupportsDenormalization()
    {
        $normalizer = new WorkflowStateNormalizer();

        $this->assertTrue($normalizer->supportsDenormalization([], WorkflowStateInterface::class));
        $this->assertFalse($normalizer->supportsDenormalization([], \stdClass::class));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new WorkflowStateNormalizer();

        $this->assertSame([WorkflowStateInterface::class => true], $normalizer->getSupportedTypes(null));
    }
}
