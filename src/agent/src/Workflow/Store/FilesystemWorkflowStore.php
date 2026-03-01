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
use Symfony\AI\Agent\Workflow\WorkflowStateNormalizer;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FilesystemWorkflowStore implements WorkflowStoreInterface, ManagedWorkflowStoreInterface
{
    public function __construct(
        private readonly string $storagePath,
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new WorkflowStateNormalizer(),
        ], [new JsonEncoder()]),
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

        $this->filesystem->touch($filename);
        $this->filesystem->dumpFile($filename, $this->serializer->serialize($state, 'json'));
    }

    public function load(string $id): ?WorkflowStateInterface
    {
        $filename = $this->getFilename($id);

        if (!$this->filesystem->exists($filename)) {
            return null;
        }

        $state = $this->filesystem->readFile($filename);

        return $this->serializer->deserialize($state, WorkflowState::class, 'json');
    }

    public function remove(string $id): void
    {
        $this->filesystem->remove($this->getFilename($id));
    }

    private function getFilename(string $id): string
    {
        return $this->storagePath.'/'.$id.'.json';
    }
}
