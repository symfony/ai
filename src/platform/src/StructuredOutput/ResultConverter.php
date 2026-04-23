<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

final class ResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly ResultConverterInterface $innerConverter,
        private readonly SerializerInterface $serializer,
        private readonly ?string $outputType = null,
        private readonly ?object $objectToPopulate = null,
    ) {
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $innerResult = $this->innerConverter->convert($result, $options);

        if (!$innerResult instanceof TextResult) {
            return $innerResult;
        }

        try {
            $context = [];
            if (null !== $this->objectToPopulate) {
                $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $this->objectToPopulate;
            }

            $content = $innerResult->getContent();

            //Some models encapsulate the structured output in a json code block, so try to extract it before decoding/deserializing
            if (str_starts_with($content, '```json') && str_ends_with($content, '```')) {
                $content = substr($content, 7, -3);
            }

            $structure = null === $this->outputType
                ? json_decode($content, true, flags: \JSON_THROW_ON_ERROR)
                : $this->serializer->deserialize(
                    $content,
                    $this->outputType,
                    'json',
                    $context
                );
        } catch (\JsonException $e) {
            throw new RuntimeException('Cannot json decode the content.', previous: $e);
        } catch (SerializerExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Cannot deserialize the content into the "%s" class.', $this->outputType), previous: $e);
        }

        $objectResult = new ObjectResult($structure);
        $objectResult->setRawResult($result);
        $objectResult->getMetadata()->set($innerResult->getMetadata()->all());

        return $objectResult;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->innerConverter->getTokenUsageExtractor();
    }
}
