<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * Normalizes and denormalizes {@see ResultInterface} instances for the cache.
 *
 * Object results rely on {@see get_debug_type()} to pick the denormalization type, which round-trips
 * arrays and stdClass but may not fully restore objects with private or readonly properties.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ResultNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly ObjectNormalizer $objectNormalizer,
    ) {
    }

    /**
     * @return array{
     *     class: string,
     *     payload: array<string, mixed>,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'class' => $data::class,
            'payload' => match ($data::class) {
                BinaryResult::class => [
                    'asBase64' => $data->toBase64(),
                    'mimeType' => $data->getMimeType(),
                ],
                ChoiceResult::class => array_map(
                    fn (ResultInterface $result): array => $this->normalize($result, $format, $context),
                    $data->getContent(),
                ),
                MultiPartResult::class => array_map(
                    fn (ResultInterface $result): array => $this->normalize($result, $format, $context),
                    $data->getContent(),
                ),
                ThinkingResult::class => [
                    'content' => $data->getContent(),
                    'signature' => $data->getSignature(),
                ],
                ObjectResult::class => [
                    'type' => get_debug_type($data->getContent()),
                    'content' => \is_array($data->getContent()) ? $data->getContent() : $this->objectNormalizer->normalize($data->getContent(), $format, $context),
                ],
                StreamResult::class => throw new InvalidArgumentException(\sprintf('"%s" cannot be normalized.', StreamResult::class)),
                TextResult::class => [
                    'content' => $data->getContent(),
                    'signature' => $data->getSignature(),
                ],
                ToolCallResult::class => array_map(
                    static fn (ToolCall $toolCall): array => [
                        'id' => $toolCall->getId(),
                        'name' => $toolCall->getName(),
                        'arguments' => $toolCall->getArguments(),
                        'signature' => $toolCall->getSignature(),
                    ],
                    $data->getContent(),
                ),
                VectorResult::class => array_map(
                    static fn (Vector $vector): array => [
                        'data' => $vector->getData(),
                        'dimensions' => $vector->getDimensions(),
                    ],
                    $data->getContent(),
                ),
                default => throw new InvalidArgumentException(\sprintf('Unsupported result type: "%s".', $data::class)),
            },
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ResultInterface;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ResultInterface
    {
        return match ($data['class']) {
            BinaryResult::class => BinaryResult::fromBase64($data['payload']['asBase64'], $data['payload']['mimeType']),
            ChoiceResult::class => new ChoiceResult(array_map(
                fn (array $choice): ResultInterface => $this->denormalize($choice, $type, $format, $context),
                $data['payload']
            )),
            MultiPartResult::class => new MultiPartResult(array_map(
                fn (array $part): ResultInterface => $this->denormalize($part, $type, $format, $context),
                $data['payload']
            )),
            ThinkingResult::class => new ThinkingResult($data['payload']['content'], $data['payload']['signature']),
            ObjectResult::class => new ObjectResult('array' === $data['payload']['type'] ? $data['payload']['content'] : $this->objectNormalizer->denormalize($data['payload']['content'], $data['payload']['type'], $format, $context)),
            TextResult::class => new TextResult($data['payload']['content'], $data['payload']['signature'] ?? null),
            ToolCallResult::class => new ToolCallResult(array_map(
                static fn (array $toolCall): ToolCall => new ToolCall(
                    $toolCall['id'],
                    $toolCall['name'],
                    $toolCall['arguments'],
                    $toolCall['signature'] ?? null,
                ),
                $data['payload'],
            )),
            VectorResult::class => new VectorResult(array_map(
                static fn (array $vector): Vector => new Vector($vector['data'], $vector['dimensions']),
                $data['payload'],
            )),
            default => throw new InvalidArgumentException(\sprintf('Unsupported result type: %s.', get_debug_type($data['class']))),
        };
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return ResultInterface::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ResultInterface::class => true,
        ];
    }
}
