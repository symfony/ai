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

final class WorkflowStateTest extends TestCase
{
    public function testConstructorDefaults()
    {
        $state = new WorkflowState('test-id');

        $this->assertSame('test-id', $state->getId());
        $this->assertSame([], $state->all());
        $this->assertSame([], $state->getCompletedPlaces());
        $this->assertNull($state->getCurrentPlace());
    }

    public function testConstructorWithData()
    {
        $state = new WorkflowState('id', ['key' => 'value'], ['place1'], 'place2');

        $this->assertSame(['key' => 'value'], $state->all());
        $this->assertSame(['place1'], $state->getCompletedPlaces());
        $this->assertSame('place2', $state->getCurrentPlace());
    }

    public function testHasAndGet()
    {
        $state = new WorkflowState('id', ['foo' => 'bar']);

        $this->assertTrue($state->has('foo'));
        $this->assertFalse($state->has('baz'));
        $this->assertSame('bar', $state->get('foo'));
        $this->assertNull($state->get('baz'));
        $this->assertSame('default', $state->get('baz', 'default'));
    }

    public function testSetReturnsSelf()
    {
        $state = new WorkflowState('id');
        $result = $state->set('key', 'value');

        $this->assertSame($state, $result);
        $this->assertSame('value', $state->get('key'));
    }

    public function testUnset()
    {
        $state = new WorkflowState('id', ['key' => 'value']);
        $result = $state->unset('key');

        $this->assertSame($state, $result);
        $this->assertFalse($state->has('key'));
    }

    public function testMerge()
    {
        $state = new WorkflowState('id', ['a' => 1]);
        $result = $state->merge(['b' => 2, 'c' => 3]);

        $this->assertSame($state, $result);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $state->all());
    }

    public function testMergeOverwritesExistingKeys()
    {
        $state = new WorkflowState('id', ['a' => 1]);
        $state->merge(['a' => 2]);

        $this->assertSame(2, $state->get('a'));
    }

    public function testWithCurrentPlace()
    {
        $state = new WorkflowState('id');
        $result = $state->withCurrentPlace('draft');

        $this->assertSame($state, $result);
        $this->assertSame('draft', $state->getCurrentPlace());

        $state->withCurrentPlace(null);
        $this->assertNull($state->getCurrentPlace());
    }

    public function testWithCompletedPlace()
    {
        $state = new WorkflowState('id');
        $result = $state->withCompletedPlace('draft');

        $this->assertSame($state, $result);
        $this->assertSame(['draft'], $state->getCompletedPlaces());

        $state->withCompletedPlace('review');
        $this->assertSame(['draft', 'review'], $state->getCompletedPlaces());
    }

    public function testNextTransition()
    {
        $state = new WorkflowState('id');

        $this->assertNull($state->getNextTransition());

        $result = $state->withNextTransition('approve');
        $this->assertSame($state, $result);
        $this->assertSame('approve', $state->getNextTransition());
    }

    public function testSerialization()
    {
        $state = new WorkflowState('id', ['key' => 'value'], ['place1'], 'place2');

        $serialized = serialize($state);
        $deserialized = unserialize($serialized);

        $this->assertInstanceOf(WorkflowState::class, $deserialized);
        $this->assertSame('id', $deserialized->getId());
        $this->assertSame(['key' => 'value'], $deserialized->all());
        $this->assertSame(['place1'], $deserialized->getCompletedPlaces());
        $this->assertSame('place2', $deserialized->getCurrentPlace());
    }
}
