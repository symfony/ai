<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput\Streaming;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\AbstractStreamListener;
use Symfony\AI\Platform\Result\Stream\CompleteEvent;
use Symfony\AI\Platform\Result\Stream\Delta\PartialObjectDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\DeltaEvent;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Buffers `TextDelta` payloads from a streaming structured-output response,
 * runs them through `PartialJsonParser`, denormalizes the recovered structure
 * with the configured serializer, and emits a `PartialObjectDelta` whenever
 * the parsed shape changes.
 *
 * On stream completion the listener also produces the final `ObjectResult`,
 * which `DeferredResult::asObject()` exposes after draining the stream.
 * If a `ValidatorInterface` is injected, the final object is validated
 * before being made available — partial snapshots are never validated.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PartialObjectStreamListener extends AbstractStreamListener
{
    private string $buffer = '';
    private ?string $lastHash = null;
    private ?ObjectResult $finalObjectResult = null;
    private ?ValidationException $validationException = null;
    private ?ValidatorInterface $validator = null;

    private readonly SerializerInterface&DenormalizerInterface $serializer;

    /**
     * @param class-string $outputType
     */
    public function __construct(
        SerializerInterface&DenormalizerInterface $serializer,
        private readonly string $outputType,
        private readonly ?object $objectToPopulate = null,
    ) {
        $this->serializer = $serializer;
    }

    public function setValidator(?ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function getFinalObjectResult(): ?ObjectResult
    {
        if (null !== $this->validationException) {
            throw $this->validationException;
        }

        return $this->finalObjectResult;
    }

    public function onDelta(DeltaEvent $event): void
    {
        $delta = $event->getDelta();

        if (!$delta instanceof TextDelta) {
            return;
        }

        $this->buffer .= $delta->getText();

        $partial = PartialJsonParser::parse($this->buffer);

        if (!\is_array($partial)) {
            return;
        }

        $hash = md5(json_encode($partial) ?: '');

        if ($hash === $this->lastHash) {
            return;
        }

        $this->lastHash = $hash;

        try {
            $object = $this->denormalize($partial);
        } catch (SerializerExceptionInterface) {
            // Partial structure does not yet satisfy the target type — wait for more deltas.
            return;
        }

        $textDelta = $delta;
        $partialDelta = new PartialObjectDelta($object, $this->buffer);
        $event->setDelta((static function () use ($textDelta, $partialDelta): \Generator {
            yield $textDelta;
            yield $partialDelta;
        })());
    }

    public function onComplete(CompleteEvent $event): void
    {
        if ('' === $this->buffer) {
            return;
        }

        try {
            $structure = $this->serializer->deserialize(
                $this->buffer,
                $this->outputType,
                'json',
                $this->buildContext(),
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('Cannot json decode the streamed content.', previous: $e);
        }

        if (null !== $this->validator) {
            $violations = $this->validator->validate($structure);

            if (0 !== \count($violations)) {
                $this->validationException = new ValidationException($violations);

                return;
            }
        }

        $this->finalObjectResult = new ObjectResult($structure);
    }

    /**
     * @param array<mixed> $structure
     */
    private function denormalize(array $structure): object
    {
        return $this->serializer->denormalize(
            $structure,
            $this->outputType,
            null,
            $this->buildContext(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(): array
    {
        $context = [];

        if (null !== $this->objectToPopulate) {
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $this->objectToPopulate;
            $context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE] = true;
        }

        return $context;
    }
}
