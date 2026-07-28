<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Skill;

/**
 * Computes a stable content hash of an installed skill directory tree.
 *
 * The hash makes copy idempotent and drift detectable: skills:install stores it in the lock and
 * skills:list recomputes it to flag stale (edited) or broken (missing) generated skills.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillContentHasher
{
    /**
     * Returns a "sha256:"-prefixed hash of every file under the directory, or null when the
     * directory does not exist.
     */
    public function hash(string $directory): ?string
    {
        if (!is_dir($directory)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        $prefixLength = \strlen($directory) + 1;
        $files = [];
        foreach ($iterator as $item) {
            \assert($item instanceof \SplFileInfo);
            if (!$item->isFile()) {
                continue;
            }

            $files[substr($item->getPathname(), $prefixLength)] = $item->getPathname();
        }

        ksort($files);

        $hash = hash_init('sha256');
        foreach ($files as $relative => $absolute) {
            $contents = file_get_contents($absolute);
            if (false === $contents) {
                $contents = '';
            }

            hash_update($hash, $relative."\0".$contents."\0");
        }

        return 'sha256:'.hash_final($hash);
    }
}
