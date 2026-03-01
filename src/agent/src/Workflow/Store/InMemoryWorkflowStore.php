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
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateNormalizer;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InMemoryWorkflowStore implements WorkflowStoreInterface, ManagedWorkflowStoreInterface
{
    /**
     * @var WorkflowStateInterface[]
     */
    private array $states = [];

    public function __construct(
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new WorkflowStateNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->states = [];
    }

    public function drop(array $options = []): void
    {
        $this->states = [];
    }

    public function save(WorkflowStateInterface $state): void
    {
        $this->states[$state->getId()] = $this->serializer->serialize($state, 'json');
    }

    public function load(string $id): ?WorkflowStateInterface
    {
        $state = $this->states[$id] ?? null;

        if (!$state instanceof WorkflowStateInterface) {
            return null;
        }

        return $this->serializer->deserialize($state, WorkflowStateInterface::class, 'json');
    }
}
