<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

/**
 * Reads a member out of an in-memory ustar archive.
 *
 * The asynchronous speech endpoint does not deliver the audio as such: it delivers a tar bundling
 * the audio with a `.titles` and an `.extra` file, and not in that order, so the wanted member has
 * to be picked by name. `PharData` would need the archive on disk plus `ext-phar`, and the archives
 * MiniMax produces are plain ustar - the 512-byte header format with a `prefix` field for the
 * directory part - so reading them directly keeps the bridge dependency-free.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
final class TarArchive
{
    private const BLOCK_SIZE = 512;

    /**
     * Returns the contents of the first regular file whose name ends in the given extension, or null
     * when the archive has no such member.
     */
    public static function findByExtension(string $archive, string $extension): ?string
    {
        $suffix = '.'.ltrim($extension, '.');
        $offset = 0;
        $length = \strlen($archive);

        while ($offset + self::BLOCK_SIZE <= $length) {
            $header = substr($archive, $offset, self::BLOCK_SIZE);
            $offset += self::BLOCK_SIZE;

            // Two consecutive zero-filled blocks terminate an archive; one is enough to stop reading.
            if ('' === rtrim($header, "\0")) {
                return null;
            }

            $size = (int) octdec(trim(substr($header, 124, 12), "\0 "));
            $typeFlag = substr($header, 156, 1);

            $name = rtrim(substr($header, 0, 100), "\0");
            $prefix = rtrim(substr($header, 345, 155), "\0");

            if ('' !== $prefix) {
                $name = $prefix.'/'.$name;
            }

            // '0' and "\0" both mark a regular file; anything else (directory, link, extended
            // header) carries no payload we are looking for.
            if (('0' === $typeFlag || "\0" === $typeFlag) && str_ends_with($name, $suffix)) {
                return substr($archive, $offset, $size);
            }

            // File contents are padded to a whole number of blocks.
            $offset += (int) (ceil($size / self::BLOCK_SIZE) * self::BLOCK_SIZE);
        }

        return null;
    }
}
