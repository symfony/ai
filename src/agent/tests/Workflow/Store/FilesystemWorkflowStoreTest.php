<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Store;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\Store\FilesystemWorkflowStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStatus;

final class FilesystemWorkflowStoreTest extends TestCase
{
    private string $tempDir;
    private FilesystemWorkflowStore $store;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/workflow_test_'.uniqid();
        mkdir($this->tempDir);
        $this->store = new FilesystemWorkflowStore($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir.'/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testSaveAndLoad(): void
    {
        $state = new WorkflowState('test-id', 'start', ['key' => 'value']);

        $this->store->save($state);
        $loadedState = $this->store->load('test-id');

        $this->assertNotNull($loadedState);
        $this->assertSame('test-id', $loadedState->getId());
        $this->assertSame('start', $loadedState->getCurrentStep());
        $this->assertSame(['key' => 'value'], $loadedState->getContext());
    }

    public function testLoadReturnsNullForNonExistentState(): void
    {
        $state = $this->store->load('non-existent');

        $this->assertNull($state);
    }

    public function testDelete(): void
    {
        $state = new WorkflowState('test-id', 'start');
        $this->store->save($state);

        $this->assertTrue($this->store->exists('test-id'));

        $this->store->delete('test-id');

        $this->assertFalse($this->store->exists('test-id'));
    }

    public function testExists(): void
    {
        $this->assertFalse($this->store->exists('test-id'));

        $state = new WorkflowState('test-id', 'start');
        $this->store->save($state);

        $this->assertTrue($this->store->exists('test-id'));
    }

    public function testSaveWithComplexData(): void
    {
        $state = new WorkflowState(
            'test-id',
            'processing',
            [
                'nested' => ['array' => 'value'],
                'number' => 42,
                'bool' => true,
            ],
            ['meta' => 'data'],
            WorkflowStatus::RUNNING
        );

        $this->store->save($state);
        $loadedState = $this->store->load('test-id');

        $this->assertNotNull($loadedState);
        $this->assertSame('processing', $loadedState->getCurrentStep());
        $this->assertSame(WorkflowStatus::RUNNING, $loadedState->getStatus());
        $this->assertSame(['nested' => ['array' => 'value'], 'number' => 42, 'bool' => true], $loadedState->getContext());
    }

    public function testAtomicWrite(): void
    {
        $state = new WorkflowState('test-id', 'start');
        $this->store->save($state);

        // Verify that temp file doesn't exist after save
        $tempFile = $this->tempDir.'/test-id.json.tmp';
        $this->assertFileDoesNotExist($tempFile);

        // Verify main file exists
        $mainFile = $this->tempDir.'/test-id.json';
        $this->assertFileExists($mainFile);
    }
}
