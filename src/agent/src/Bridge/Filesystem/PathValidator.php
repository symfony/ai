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
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

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
     * @param list<string> $deniedPatterns    Glob patterns for files to deny (e.g., ['.*', '*.env*', '.git/*']), matched against the base-relative path and each of its ancestor directories.
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

    /**
     * Validates that no path inside an already validated directory matches a denied pattern, also when relocated to an already validated destination.
     *
     * @throws PathSecurityException If a contained path violates security constraints
     */
    public function validateDirectoryContents(string $directory, ?string $destination = null): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $prefixes = [$this->makeRelative($directory)];

        if (null !== $destination) {
            $prefixes[] = $this->makeRelative($destination);
        }

        $finder = new Finder();
        $finder->in($directory)
            ->ignoreDotFiles(false)
            ->ignoreVCS(false);

        // Ancestors are visited by the finder as well, so each entry only needs its own name and path checked
        foreach ($finder as $item) {
            $relativePathname = str_replace(\DIRECTORY_SEPARATOR, '/', $item->getRelativePathname());

            foreach ($prefixes as $prefix) {
                $relativePath = ltrim($prefix.'/'.$relativePathname, '/');
                $this->assertNotDenied($relativePath, $relativePath, $item->getFilename());
            }
        }
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

        $base = $realBasePath;
        $candidate = $resolvedPath;

        // On Windows, realpath() yields backslashes while non-existing paths are
        // assembled with "/", and the filesystem is case-insensitive. Normalize both
        // so the directory boundary is detected reliably. On Unix the backslash is a
        // valid filename character, so it must be left untouched there.
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $base = strtolower(str_replace('\\', '/', $base));
            $candidate = strtolower(str_replace('\\', '/', $candidate));
        }

        $base = rtrim($base, '/');

        if ($candidate !== $base && !str_starts_with($candidate, $base.'/')) {
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
        $relativePath = $this->makeRelative($path);

        if ('' === $relativePath) {
            return;
        }

        $segments = explode('/', $relativePath);

        // The path itself and each of its ancestor directories
        foreach ($segments as $i => $segment) {
            $this->assertNotDenied($relativePath, implode('/', \array_slice($segments, 0, $i + 1)), $segment);
        }
    }

    private function assertNotDenied(string $relativePath, string $candidate, string $name): void
    {
        foreach ($this->deniedPatterns as $pattern) {
            if (fnmatch($pattern, $name) || fnmatch($pattern, $candidate, \FNM_PATHNAME)) {
                throw new PathSecurityException(\sprintf('Path "%s" matches denied pattern "%s".', $relativePath, $pattern));
            }
        }
    }

    private function makeRelative(string $path): string
    {
        $basePath = realpath($this->basePath);

        if (false === $basePath) {
            throw new PathSecurityException(\sprintf('Base path "%s" does not exist.', $this->basePath));
        }

        return Path::makeRelative($path, $basePath);
    }
}
