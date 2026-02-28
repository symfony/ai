<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Store;

use Symfony\AI\Agent\Workflow\ManagedWorkflowStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FilesystemWorkflowStore implements WorkflowStoreInterface, ManagedWorkflowStoreInterface
{
    private array $locks = [];

    public function __construct(
        private readonly string $storagePath,
        private readonly bool $useLocking = true,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->filesystem->mkdir($this->storagePath, 0755, true);
    }

    public function drop(array $options = []): void
    {
        $this->filesystem->remove($this->storagePath);
    }

    public function save(WorkflowStateInterface $state): void
    {
        $filename = $this->getFilename($state->getId());
        $lockFile = $filename.'.lock';

        if ($this->useLocking) {
            $lock = fopen($lockFile, 'c+');
            if (!flock($lock, \LOCK_EX)) {
                throw new \RuntimeException('Could not acquire lock for workflow '.$state->getId());
            }
        }

        try {
            $tempFile = $filename.'.tmp';
            file_put_contents(
                $tempFile,
                json_encode($state->toArray(), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)
            );
            rename($tempFile, $filename);
        } finally {
            if ($this->useLocking && isset($lock)) {
                flock($lock, \LOCK_UN);
                fclose($lock);
                @unlink($lockFile);
            }
        }
    }

    public function load(string $id): ?WorkflowStateInterface
    {
        $filename = $this->getFilename($id);

        if (!$this->filesystem->exists($filename)) {
            return null;
        }

        $data = json_decode(file_get_contents($filename), true, 512, \JSON_THROW_ON_ERROR);

        return WorkflowState::fromArray($data);
    }

    public function delete(string $id): void
    {
        $filename = $this->getFilename($id);

        $this->filesystem->remove($filename);
    }

    public function exists(string $id): bool
    {
        return $this->filesystem->exists($this->getFilename($id));
    }

    private function getFilename(string $id): string
    {
        return $this->storagePath.'/'.$id.'.json';
    }
}
