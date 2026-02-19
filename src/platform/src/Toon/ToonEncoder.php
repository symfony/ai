<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Toon;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Integration of Toon into Symfony Serializer
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToonEncoder implements EncoderInterface, DecoderInterface
{
    public const FORMAT = 'toon';

    /**
     * @param array{delimiter?: string, indent_size?: int, strict?: bool} $defaultContext
     */
    public function __construct(
        private Toon $toon = new Toon(),
        private array $defaultContext = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function encode(mixed $data, string $format, array $context = []): string
    {
        $indentSize = (int) ($context['indent_size'] ?? $this->defaultContext['indent_size'] ?? Toon::DEFAULT_INDENT_SIZE);
        $delimiter = (string) ($context['delimiter'] ?? $this->defaultContext['delimiter'] ?? Toon::DEFAULT_DELIMITER);

        return $this->toon->encode($data, 0, $indentSize, $delimiter);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function decode(string $data, string $format, array $context = []): mixed
    {
        $strict = (bool) ($context['strict'] ?? $this->defaultContext['strict'] ?? true);
        $indentSize = (int) ($context['indent_size'] ?? $this->defaultContext['indent_size'] ?? Toon::DEFAULT_INDENT_SIZE);

        return $this->toon->decode($data, $indentSize, $strict);
    }

    public function supportsEncoding(string $format): bool
    {
        return self::FORMAT === $format;
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }
}
