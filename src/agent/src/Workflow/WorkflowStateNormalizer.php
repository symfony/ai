<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Serializes and deserializes a {@see WorkflowStateInterface}.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof WorkflowStateInterface) {
            return [];
        }

        return [
            'id' => $data->getId(),
            'data' => $data->all(),
            'completed_places' => $data->getCompletedPlaces(),
            'current_place' => $data->getCurrentPlace(),
            'next_transition' => $data->getNextTransition(),
            'interrupted_fork' => $data->getInterruptedFork(),
            'updated_at' => $this->clock->now()->getTimestamp(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof WorkflowStateInterface;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): WorkflowStateInterface
    {
        if (!\is_array($data) || !isset($data['id'])) {
            throw new InvalidArgumentException('Cannot denormalize workflow state: the "id" key is missing.');
        }

        $updatedAt = null;
        if (isset($data['updated_at']) && is_numeric($data['updated_at'])) {
            $updatedAt = (new \DateTimeImmutable())->setTimestamp((int) $data['updated_at']);
        }

        return new WorkflowState(
            $data['id'],
            $data['data'] ?? [],
            $data['completed_places'] ?? [],
            $data['current_place'] ?? null,
            $data['next_transition'] ?? null,
            $data['interrupted_fork'] ?? [],
            $updatedAt,
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return WorkflowStateInterface::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            WorkflowStateInterface::class => true,
        ];
    }
}
