<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Bridge\Filesystem;

use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\AbstractWorkflowStateStore;
use Symfony\AI\Agent\Workflow\ListableWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Workflow state store backed by the filesystem.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateStore extends AbstractWorkflowStateStore implements ListableWorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $directory,
    ) {
        parent::__construct();
    }

    public function setup(): void
    {
        $this->filesystem->mkdir($this->directory);
    }

    public function drop(): void
    {
        if (!$this->filesystem->exists($this->directory)) {
            return;
        }

        $files = glob($this->directory.\DIRECTORY_SEPARATOR.'*.workflow');

        if (false !== $files && [] !== $files) {
            $this->filesystem->remove($files);
        }
    }

    public function save(WorkflowStateInterface $state): void
    {
        $this->filesystem->dumpFile($this->getPath($state->getId()), $this->serializer->serialize($state, 'json'));
    }

    public function load(string $id): WorkflowStateInterface
    {
        $path = $this->getPath($id);

        if (!$this->filesystem->exists($path)) {
            throw new WorkflowStateNotFoundException(\sprintf('Workflow state with id "%s" not found.', $id));
        }

        return $this->serializer->deserialize($this->filesystem->readFile($path), WorkflowStateInterface::class, 'json');
    }

    public function has(string $id): bool
    {
        return $this->filesystem->exists($this->getPath($id));
    }

    public function delete(string $id): void
    {
        $path = $this->getPath($id);

        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }

    public function list(): iterable
    {
        if (!$this->filesystem->exists($this->directory)) {
            return;
        }

        $files = glob($this->directory.\DIRECTORY_SEPARATOR.'*.workflow');

        if (false === $files) {
            return;
        }

        // Filenames are hashed, so the id is recovered from each file's content.
        foreach ($files as $file) {
            $decoded = json_decode($this->filesystem->readFile($file), true);

            if (\is_array($decoded) && isset($decoded['id']) && \is_string($decoded['id'])) {
                yield $decoded['id'];
            }
        }
    }

    private function getPath(string $id): string
    {
        return $this->directory.\DIRECTORY_SEPARATOR.hash('xxh128', $id).'.workflow';
    }
}
