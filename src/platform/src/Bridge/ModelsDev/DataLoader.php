<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Loads and caches models.dev data.
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class DataLoader
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $cachedData = null;

    private static ?string $cachedPath = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function load(?string $dataPath = null): array
    {
        $dataPath ??= __DIR__.'/Resources/config/models-dev.json';

        // Return cached data if path matches
        if (null !== self::$cachedData && self::$cachedPath === $dataPath) {
            return self::$cachedData;
        }

        if (!file_exists($dataPath)) {
            throw new RuntimeException(\sprintf('The models.dev data file "%s" does not exist.', $dataPath));
        }

        $json = file_get_contents($dataPath);
        if (false === $json) {
            throw new RuntimeException(\sprintf('Failed to read the models.dev data file "%s".', $dataPath));
        }

        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            throw new RuntimeException('Invalid models.dev API data.');
        }

        self::$cachedData = $data;
        self::$cachedPath = $dataPath;

        return $data;
    }

    /**
     * Clear cached data (useful for testing).
     *
     * @internal
     */
    public static function clearCache(): void
    {
        self::$cachedData = null;
        self::$cachedPath = null;
    }
}
