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

use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final readonly class ToolCallArgumentResolver
{
    public function __construct(
        private DenormalizerInterface $denormalizer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()]),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveArguments(Tool $metadata, ToolCall $toolCall): array
    {
        $method = new \ReflectionMethod($metadata->reference->class, $metadata->reference->method);

        /** @var array<string, \ReflectionProperty> $parameters */
        $parameters = array_column($method->getParameters(), null, 'name');
        $arguments = [];

        foreach ($toolCall->arguments as $name => $value) {
            $parameterType = (string) $parameters[$name]->getType();
            $arguments[$name] = 'array' === $parameterType ? $value : $this->denormalizer->denormalize($value, $parameterType);
        }

        return $arguments;
    }
}
