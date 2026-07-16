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

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Base class for {@see WorkflowStateStoreInterface} implementations,
 * providing the shared serializer used to (de)serialize workflow state.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class AbstractWorkflowStateStore implements WorkflowStateStoreInterface
{
    protected readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([
            new ArrayDenormalizer(),
            new WorkflowStateNormalizer(),
        ], [new JsonEncoder()]);
    }
}
