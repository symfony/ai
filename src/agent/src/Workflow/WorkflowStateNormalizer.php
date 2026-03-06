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

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof WorkflowStateInterface) {
            return [];
        }

        return [
            'id' => $data->getId(),
            'data' => [
                ...$data->all(),
                'normalized_at' => $this->clock->now()->getTimestamp(),
            ],
            'completed_places' => $data->getCompletedPlaces(),
            'current_place' => $data->getCurrentPlace(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof WorkflowStateInterface;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): WorkflowStateInterface
    {
        return new WorkflowState($data['id'], $data['data'], $data['completed_places'], $data['current_place']);
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
