<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\InMemory;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateNormalizer;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface, ResetInterface
{
    /**
     * @var array<string, array<string, mixed>>
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
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->states = [];
    }

    public function drop(array $options = []): void
    {
        $this->states = [];
    }

    public function save(WorkflowStateInterface $state): void
    {
        $this->states[$state->getId()] = $this->serializer->normalize($state);
    }

    public function load(string $id): WorkflowStateInterface
    {
        if (!$this->has($id)) {
            throw new WorkflowStateNotFoundException(\sprintf('Workflow state with id "%s" not found.', $id));
        }

        return $this->serializer->denormalize($this->states[$id], WorkflowStateInterface::class);
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->states);
    }

    public function delete(string $id): void
    {
        unset($this->states[$id]);
    }

    public function reset(): void
    {
        $this->states = [];
    }
}
