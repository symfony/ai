<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PHPStan\Deprecation;

/**
 * Finds the Composer package a source file belongs to.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PackageResolver
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    public static function packageOf(string $file): ?string
    {
        $dir = \dirname($file);
        $visited = [];
        while (!isset(self::$cache[$dir])) {
            $visited[] = $dir;
            if (is_file($dir.'/composer.json')) {
                $data = json_decode((string) file_get_contents($dir.'/composer.json'), true);
                self::$cache[$dir] = \is_array($data) && \is_string($data['name'] ?? null) ? $data['name'] : null;
                break;
            }
            $parent = \dirname($dir);
            if ($parent === $dir) {
                self::$cache[$dir] = null;
                break;
            }
            $dir = $parent;
        }

        foreach ($visited as $path) {
            self::$cache[$path] = self::$cache[$dir];
        }

        return self::$cache[$dir];
    }

    public static function isTestFile(string $file): bool
    {
        return 1 === preg_match('#/(Tests|tests)/#', str_replace('\\', '/', $file));
    }
}
