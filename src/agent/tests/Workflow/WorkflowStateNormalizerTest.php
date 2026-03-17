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

        $state = new WorkflowState('wf-1', ['foo' => 'bar'], ['place_a'], 'place_b');

        $result = $normalizer->normalize($state);

        $this->assertSame('wf-1', $result['id']);
        $this->assertSame('bar', $result['data']['foo']);
        $this->assertSame($clock->now()->getTimestamp(), $result['data']['normalized_at']);
        $this->assertSame(['place_a'], $result['completed_places']);
        $this->assertSame('place_b', $result['current_place']);
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
        ];

        $state = $normalizer->denormalize($data, WorkflowStateInterface::class);

        $this->assertSame('wf-2', $state->getId());
        $this->assertSame(['key' => 'value'], $state->all());
        $this->assertSame(['step_one'], $state->getCompletedPlaces());
        $this->assertSame('step_two', $state->getCurrentPlace());
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
