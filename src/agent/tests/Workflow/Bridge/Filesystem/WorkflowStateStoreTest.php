<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Bridge\Filesystem;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\Bridge\Filesystem\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\Component\Filesystem\Filesystem;

final class WorkflowStateStoreTest extends TestCase
{
    private string $directory;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir().'/workflow_state_test_'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testSaveAndLoad()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);
        $state = new WorkflowState('test-id', ['key' => 'value']);

        $store->save($state);

        $loaded = $store->load('test-id');
        $this->assertSame('test-id', $loaded->getId());
        $this->assertSame('value', $loaded->get('key'));
    }

    public function testLoadThrowsWhenNotFound()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);

        $this->expectException(WorkflowStateNotFoundException::class);

        $store->load('nonexistent');
    }

    public function testHas()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);

        $this->assertFalse($store->has('test-id'));

        $store->save(new WorkflowState('test-id'));

        $this->assertTrue($store->has('test-id'));
    }

    public function testDelete()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);
        $store->save(new WorkflowState('test-id'));

        $this->assertTrue($store->has('test-id'));

        $store->delete('test-id');

        $this->assertFalse($store->has('test-id'));
    }

    public function testSetupCreatesDirectory()
    {
        $newDirectory = $this->directory.'/nested';
        $store = new WorkflowStateStore($this->filesystem, $newDirectory);

        $store->setup();

        $this->assertDirectoryExists($newDirectory);
    }

    public function testDropRemovesAllWorkflowFiles()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);
        $store->save(new WorkflowState('id-a'));
        $store->save(new WorkflowState('id-b'));

        $store->drop();

        $this->assertFalse($store->has('id-a'));
        $this->assertFalse($store->has('id-b'));
    }

    public function testListIsEmptyOnFreshStore()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);

        $this->assertSame([], array_values(iterator_to_array($store->list())));
    }

    public function testListYieldsBothIdsAfterTwoSaves()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);
        $store->save(new WorkflowState('id-a'));
        $store->save(new WorkflowState('id-b'));

        $ids = array_values(iterator_to_array($store->list()));
        sort($ids);

        $this->assertSame(['id-a', 'id-b'], $ids);
    }

    public function testListYieldsOnlyRemainingIdAfterDelete()
    {
        $store = new WorkflowStateStore($this->filesystem, $this->directory);
        $store->save(new WorkflowState('id-a'));
        $store->save(new WorkflowState('id-b'));
        $store->delete('id-a');

        $ids = array_values(iterator_to_array($store->list()));

        $this->assertSame(['id-b'], $ids);
    }
}
