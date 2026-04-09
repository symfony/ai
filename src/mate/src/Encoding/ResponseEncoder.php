<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Encoding;

use HelgeSverre\Toon\Toon;

/**
 * Encodes data for MCP tool responses.
 *
 * Uses TOON (Token-Oriented Object Notation) format when the helgesverre/toon
 * package is installed, falling back to JSON otherwise.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResponseEncoder
{
    /**
     * Encodes data for MCP tool responses using TOON if available, JSON otherwise.
     */
    public static function encode(mixed $data): string
    {
        if (class_exists(Toon::class)) {
            return Toon::encode($data);
        }

        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decodes a previously encoded response back to structured data.
     *
     * Tries TOON decoding first (if available), then JSON.
     */
    public static function decode(string $data): mixed
    {
        if (class_exists(Toon::class)) {
            return Toon::decode($data);
        }

        return json_decode($data, true, 512, \JSON_THROW_ON_ERROR);
    }
}
