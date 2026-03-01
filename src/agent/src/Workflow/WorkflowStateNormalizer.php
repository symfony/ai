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

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // TODO: Implement denormalize() method.
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // TODO: Implement supportsDenormalization() method.
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$data instanceof WorkflowStateInterface) {
            return [];
        }

        return [
            'id' => $data->getId(),
            'currentStep' => $data->getCurrentStep(),
            'context' => $data->getContext(),
            'metadata' => $data->getMetadata(),
            'status' => $data->getStatus()->value,
            'errors' => array_map(static fn (WorkflowError $e): array => $e->toArray(), $data->getErrors()),
            'createdAt' => $data->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $data->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof WorkflowStateInterface;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            WorkflowStateInterface::class => true,
        ];
    }
}
