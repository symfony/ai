<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Filesystem;

use Symfony\AI\Agent\Bridge\Filesystem\Exception\PathSecurityException;

/**
 * Validates paths against security constraints.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PathValidator
{
    /**
     * @param list<string> $allowedExtensions Extensions that are allowed (e.g., ['txt', 'md']). Empty means all allowed.
     * @param list<string> $deniedExtensions  Extensions that are denied (e.g., ['php', 'exe']).
     * @param list<string> $deniedPatterns    Glob patterns for files to deny (e.g., ['.*', '*.env*']).
     */
    public function __construct(
        private readonly string $basePath,
        private readonly array $allowedExtensions = [],
        private readonly array $deniedExtensions = ['php', 'phar', 'sh', 'exe', 'bat'],
        private readonly array $deniedPatterns = ['.*', '*.env*'],
    ) {
    }

    /**
     * Validates a path and returns the real, resolved path.
     *
     * @throws PathSecurityException If the path is invalid or violates security constraints
     */
    public function validate(string $path, bool $mustExist = true): string
    {
        $resolvedPath = $this->resolvePath($path, $mustExist);

        $this->assertWithinBasePath($resolvedPath);
        $this->assertExtensionAllowed($resolvedPath);
        $this->assertNotDeniedPattern($resolvedPath);

        return $resolvedPath;
    }

    /**
     * Validates a path for a directory.
     *
     * @throws PathSecurityException If the path is invalid or violates security constraints
     */
    public function validateDirectory(string $path, bool $mustExist = true): string
    {
        $resolvedPath = $this->resolvePath($path, $mustExist);

        $this->assertWithinBasePath($resolvedPath);
        $this->assertNotDeniedPattern($resolvedPath);

        return $resolvedPath;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    private function resolvePath(string $path, bool $mustExist): string
    {
        // Handle absolute paths by checking if they're within base path
        if (str_starts_with($path, '/')) {
            $fullPath = $path;
        } else {
            $fullPath = $this->basePath.'/'.$path;
        }

        // Check for path traversal attempts in the input
        if (str_contains($path, '..')) {
            throw new PathSecurityException(\sprintf('Path traversal detected in "%s".', $path));
        }

        if ($mustExist) {
            $realPath = realpath($fullPath);

            if (false === $realPath) {
                throw new PathSecurityException(\sprintf('Path "%s" does not exist.', $path));
            }

            return $realPath;
        }

        // For non-existing paths, resolve the parent and construct the full path
        $parentDir = \dirname($fullPath);
        $basename = basename($fullPath);
        $realParent = realpath($parentDir);

        if (false === $realParent) {
            throw new PathSecurityException(\sprintf('Parent directory of "%s" does not exist.', $path));
        }

        return $realParent.'/'.$basename;
    }

    private function assertWithinBasePath(string $resolvedPath): void
    {
        $realBasePath = realpath($this->basePath);

        if (false === $realBasePath) {
            throw new PathSecurityException(\sprintf('Base path "%s" does not exist.', $this->basePath));
        }

        if (!str_starts_with($resolvedPath, $realBasePath)) {
            throw new PathSecurityException(\sprintf('Path "%s" is outside the allowed base path.', $resolvedPath));
        }
    }

    private function assertExtensionAllowed(string $path): void
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        // If no extension, it might be a directory or extensionless file
        if ('' === $extension) {
            return;
        }

        // Check allowed extensions (if configured)
        if ([] !== $this->allowedExtensions && !\in_array($extension, $this->allowedExtensions, true)) {
            throw new PathSecurityException(\sprintf('Extension "%s" is not in the allowed list.', $extension));
        }

        // Check denied extensions
        if (\in_array($extension, $this->deniedExtensions, true)) {
            throw new PathSecurityException(\sprintf('Extension "%s" is not allowed.', $extension));
        }
    }

    private function assertNotDeniedPattern(string $path): void
    {
        $filename = basename($path);

        foreach ($this->deniedPatterns as $pattern) {
            if (fnmatch($pattern, $filename)) {
                throw new PathSecurityException(\sprintf('Path "%s" matches denied pattern "%s".', $filename, $pattern));
            }
        }
    }
}
