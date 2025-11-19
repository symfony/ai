<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ToolCallArgumentResolver
{
    private readonly TypeResolver $typeResolver;

    public function __construct(
        private readonly DenormalizerInterface $denormalizer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer(), new ArrayDenormalizer()]),
        ?TypeResolver $typeResolver = null,
    ) {
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ToolException When a mandatory tool parameter is missing from the tool call arguments
     */
    public function resolveArguments(Tool $metadata, ToolCall $toolCall): array
    {
        $method = new \ReflectionMethod($metadata->getReference()->getClass(), $metadata->getReference()->getMethod());

        /** @var array<string, \ReflectionParameter> $parameters */
        $parameters = array_column($method->getParameters(), null, 'name');
        $arguments = [];

        foreach ($parameters as $name => $reflectionParameter) {
            if (!\array_key_exists($name, $toolCall->getArguments())) {
                if (!$reflectionParameter->isOptional()) {
                    throw new ToolException(\sprintf('Parameter "%s" is mandatory for tool "%s".', $name, $toolCall->getName()));
                }
                continue;
            }

            $value = $toolCall->getArguments()[$name];
            $parameterType = $this->typeResolver->resolve($reflectionParameter);
            $dimensions = '';
            while ($parameterType instanceof CollectionType) {
                $dimensions .= '[]';
                $parameterType = $parameterType->getCollectionValueType();
            }

            $parameterType .= $dimensions;

            if ($this->denormalizer->supportsDenormalization($value, $parameterType)) {
                $value = $this->denormalizer->denormalize($value, $parameterType);
            }

            $arguments[$name] = $value;
        }

        return $arguments;
    }
}
