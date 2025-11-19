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

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolResultConverter
{
    public function __construct(
        private readonly SerializerInterface $serializer = new Serializer([new JsonSerializableNormalizer(), new DateTimeNormalizer(), new ObjectNormalizer()], [new JsonEncoder()]),
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function convert(ToolResult $toolResult): ?string
    {
        $result = $toolResult->getResult();

        if (null === $result || \is_string($result)) {
            return $result;
        }

        if ($result instanceof \Stringable) {
            return (string) $result;
        }

        try {
            return $this->serializer->serialize($result, 'json');
        } catch (SerializerExceptionInterface $e) {
            throw new RuntimeException('Cannot serialize the tool result.', previous: $e);
        }
    }
}
