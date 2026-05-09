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
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\MultiPartResult;
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

        if ($innerResult instanceof TextResult) {
            return $this->convertTextToObject($innerResult, $result);
        }

        if ($innerResult instanceof MultiPartResult) {
            return $this->convertMultiPart($innerResult, $result);
        }

        if ($innerResult instanceof ChoiceResult) {
            return $this->convertChoice($innerResult, $result);
        }

        return $innerResult;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->innerConverter->getTokenUsageExtractor();
    }

    private function convertTextToObject(TextResult $textResult, RawResultInterface $result): ObjectResult
    {
        try {
            $context = [];
            if (null !== $this->objectToPopulate) {
                $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $this->objectToPopulate;
            }

            $structure = null === $this->outputType
                ? json_decode($textResult->getContent(), true, flags: \JSON_THROW_ON_ERROR)
                : $this->serializer->deserialize(
                    $textResult->getContent(),
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
        $objectResult->getMetadata()->set($textResult->getMetadata()->all());

        // Preserve the provider-scoped signature (Vertex/Gemini) alongside the metadata so
        // it survives the swap from TextResult to ObjectResult and can still be replayed.
        if (null !== $signature = $textResult->getSignature()) {
            $objectResult->getMetadata()->add('signature', $signature);
        }

        return $objectResult;
    }

    private function convertMultiPart(MultiPartResult $multiPart, RawResultInterface $result): MultiPartResult
    {
        $parts = $multiPart->getContent();
        $converted = false;
        $newParts = [];

        foreach ($parts as $part) {
            if (!$converted && $part instanceof TextResult) {
                $newParts[] = $this->convertTextToObject($part, $result);
                $converted = true;

                continue;
            }

            $newParts[] = $part;
        }

        if (!$converted) {
            return $multiPart;
        }

        $rebuilt = new MultiPartResult($newParts);
        $rebuilt->setRawResult($result);
        $rebuilt->getMetadata()->set($multiPart->getMetadata()->all());

        return $rebuilt;
    }

    private function convertChoice(ChoiceResult $choices, RawResultInterface $result): ChoiceResult
    {
        $newChoices = [];
        $converted = false;

        foreach ($choices->getContent() as $choice) {
            if ($choice instanceof TextResult) {
                $newChoices[] = $this->convertTextToObject($choice, $result);
                $converted = true;

                continue;
            }

            if ($choice instanceof MultiPartResult) {
                $convertedChoice = $this->convertMultiPart($choice, $result);
                if ($convertedChoice !== $choice) {
                    $converted = true;
                }
                $newChoices[] = $convertedChoice;

                continue;
            }

            $newChoices[] = $choice;
        }

        if (!$converted) {
            return $choices;
        }

        $rebuilt = new ChoiceResult($newChoices);
        $rebuilt->setRawResult($result);
        $rebuilt->getMetadata()->set($choices->getMetadata()->all());

        return $rebuilt;
    }
}
