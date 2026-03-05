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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateNormalizer;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
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
final class WorkflowStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $directory,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new WorkflowStateNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->filesystem->mkdir($this->directory);
    }

    public function drop(array $options = []): void
    {
        $this->filesystem->remove($this->directory.'/*.workflow');
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

    private function getPath(string $id): string
    {
        return $this->directory.\DIRECTORY_SEPARATOR.hash('xxh128', $id).'.workflow';
    }
}
