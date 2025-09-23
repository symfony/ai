<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\StructuredOutput;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentProcessor implements InputProcessorInterface, OutputProcessorInterface
{
    private string $outputStructure;

    public function __construct(
        private readonly ResponseFormatFactoryInterface $responseFormatFactory = new ResponseFormatFactory(),
        private ?SerializerInterface $serializer = null,
    ) {
        if (null === $this->serializer) {
            $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
            $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
            $propertyInfo = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

            $normalizers = [
                new BackedEnumNormalizer(),
                new ObjectNormalizer(
                    classMetadataFactory: $classMetadataFactory,
                    propertyTypeExtractor: $propertyInfo,
                    classDiscriminatorResolver: $discriminator
                ),
                new ArrayDenormalizer(),
            ];

            $this->serializer = new Serializer($normalizers, [new JsonEncoder()]);
        }
    }

    /**
     * @throws MissingModelSupportException When structured output is requested but the model doesn't support it
     * @throws InvalidArgumentException     When streaming is enabled with structured output (incompatible options)
     */
    public function processInput(Input $input): void
    {
        $options = $input->getOptions();

        if (!isset($options['output_structure'])) {
            return;
        }

        if (!$input->model->supports(Capability::OUTPUT_STRUCTURED)) {
            throw MissingModelSupportException::forStructuredOutput($input->model::class);
        }

        if (true === ($options['stream'] ?? false)) {
            throw new InvalidArgumentException('Streamed responses are not supported for structured output.');
        }

        $options['response_format'] = $this->responseFormatFactory->create($options['output_structure']);

        $this->outputStructure = $options['output_structure'];
        unset($options['output_structure']);

        $input->setOptions($options);
    }

    public function processOutput(Output $output): void
    {
        $options = $output->options;

        if ($output->result instanceof ObjectResult) {
            return;
        }

        if (!isset($options['response_format'])) {
            return;
        }

        if (!isset($this->outputStructure)) {
            $output->result = new ObjectResult(json_decode($output->result->getContent(), true));

            return;
        }

        $originalResult = $output->result;
        $output->result = new ObjectResult(
            $this->serializer->deserialize($output->result->getContent(), $this->outputStructure, 'json')
        );

        if ($originalResult->getMetadata()->count() > 0) {
            $output->result->getMetadata()->set($originalResult->getMetadata()->all());
        }

        if (!empty($originalResult->getRawResult())) {
            $output->result->setRawResult($originalResult->getRawResult());
        }
    }
}
