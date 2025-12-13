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
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
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
     * @return ContentInterface[]
     *
     * @throws RuntimeException
     */
    public function convert(ToolResult $toolResult): array
    {
        $result = $toolResult->getResult();

        if (null === $result) {
            return [];
        }

        // Already ContentInterface[] - pass through
        if (\is_array($result) && $this->isContentArray($result)) {
            return $result;
        }

        if (\is_string($result)) {
            return [new Text($result)];
        }

        if ($result instanceof \Stringable) {
            return [new Text((string) $result)];
        }

        try {
            return [new Text($this->serializer->serialize($result, 'json'))];
        } catch (SerializerExceptionInterface $e) {
            throw new RuntimeException('Cannot serialize the tool result.', previous: $e);
        }
    }

    /**
     * @param array<mixed> $array
     */
    private function isContentArray(array $array): bool
    {
        foreach ($array as $item) {
            if (!$item instanceof ContentInterface) {
                return false;
            }
        }

        return true;
    }
}
