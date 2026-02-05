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

use Symfony\AI\Agent\Bridge\Filesystem\Exception\OperationNotPermittedException;
use Symfony\AI\Agent\Bridge\Filesystem\Exception\PathSecurityException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Finder\Finder;

/**
 * Provides filesystem operations as AI tools.
 *
 * All operations are sandboxed to a configurable base directory with security safeguards
 * including path validation, extension filtering, and operation permissions.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('filesystem_read', description: 'Read the content of a file.', method: 'read')]
#[AsTool('filesystem_write', description: 'Write content to a file (creates or overwrites).', method: 'write')]
#[AsTool('filesystem_append', description: 'Append content to a file.', method: 'append')]
#[AsTool('filesystem_copy', description: 'Copy a file to a new location.', method: 'copy')]
#[AsTool('filesystem_move', description: 'Move or rename a file or directory.', method: 'move')]
#[AsTool('filesystem_delete', description: 'Delete a file or directory.', method: 'delete')]
#[AsTool('filesystem_mkdir', description: 'Create a directory.', method: 'mkdir')]
#[AsTool('filesystem_exists', description: 'Check if a file or directory exists.', method: 'exists')]
#[AsTool('filesystem_info', description: 'Get file metadata (size, modified time, permissions).', method: 'info')]
#[AsTool('filesystem_list', description: 'List contents of a directory.', method: 'list')]
final class Filesystem
{
    private readonly PathValidator $pathValidator;

    /**
     * @param list<string> $allowedExtensions Extensions that are allowed (e.g., ['txt', 'md']). Empty means all allowed.
     * @param list<string> $deniedExtensions  Extensions that are denied (e.g., ['php', 'exe']).
     * @param list<string> $deniedPatterns    Glob patterns for files to deny (e.g., ['.*', '*.env*']).
     */
    public function __construct(
        private readonly SymfonyFilesystem $filesystem,
        string $basePath,
        private readonly bool $allowWrite = true,
        private readonly bool $allowDelete = false,
        array $allowedExtensions = [],
        array $deniedExtensions = ['php', 'phar', 'sh', 'exe', 'bat'],
        array $deniedPatterns = ['.*', '*.env*'],
        private readonly int $maxReadSize = 10485760,
    ) {
        $this->pathValidator = new PathValidator($basePath, $allowedExtensions, $deniedExtensions, $deniedPatterns);
    }

    /**
     * Read the content of a file.
     *
     * @throws PathSecurityException If the path violates security constraints
     */
    public function read(string $path): string
    {
        $resolvedPath = $this->pathValidator->validate($path);

        if (!is_file($resolvedPath)) {
            throw new PathSecurityException(\sprintf('Path "%s" is not a file.', $path));
        }

        $fileSize = filesize($resolvedPath);

        if (false === $fileSize) {
            throw new PathSecurityException(\sprintf('Cannot determine size of file "%s".', $path));
        }

        if ($fileSize > $this->maxReadSize) {
            throw new PathSecurityException(\sprintf('File "%s" exceeds maximum read size of %d bytes.', $path, $this->maxReadSize));
        }

        $content = file_get_contents($resolvedPath);

        if (false === $content) {
            throw new PathSecurityException(\sprintf('Cannot read file "%s".', $path));
        }

        return $content;
    }

    /**
     * Write content to a file (creates or overwrites).
     *
     * @throws OperationNotPermittedException If write operations are not allowed
     * @throws PathSecurityException          If the path violates security constraints
     */
    public function write(string $path, string $content): string
    {
        $this->assertWriteAllowed();

        $resolvedPath = $this->pathValidator->validate($path, mustExist: false);

        $this->filesystem->dumpFile($resolvedPath, $content);

        return \sprintf('Successfully wrote %d bytes to "%s".', \strlen($content), $path);
    }

    /**
     * Append content to a file.
     *
     * @throws OperationNotPermittedException If write operations are not allowed
     * @throws PathSecurityException          If the path violates security constraints
     */
    public function append(string $path, string $content): string
    {
        $this->assertWriteAllowed();

        $resolvedPath = $this->pathValidator->validate($path, mustExist: false);

        $this->filesystem->appendToFile($resolvedPath, $content);

        return \sprintf('Successfully appended %d bytes to "%s".', \strlen($content), $path);
    }

    /**
     * Copy a file to a new location.
     *
     * @throws OperationNotPermittedException If write operations are not allowed
     * @throws PathSecurityException          If the path violates security constraints
     */
    public function copy(string $source, string $destination): string
    {
        $this->assertWriteAllowed();

        $resolvedSource = $this->pathValidator->validate($source);
        $resolvedDestination = $this->pathValidator->validate($destination, mustExist: false);

        $this->filesystem->copy($resolvedSource, $resolvedDestination, overwriteNewerFiles: true);

        return \sprintf('Successfully copied "%s" to "%s".', $source, $destination);
    }

    /**
     * Move or rename a file or directory.
     *
     * @throws OperationNotPermittedException If write operations are not allowed
     * @throws PathSecurityException          If the path violates security constraints
     */
    public function move(string $source, string $destination): string
    {
        $this->assertWriteAllowed();

        if (is_dir($source)) {
            $resolvedSource = $this->pathValidator->validateDirectory($source);
            $resolvedDestination = $this->pathValidator->validateDirectory($destination, mustExist: false);
        } else {
            $resolvedSource = $this->pathValidator->validate($source);
            $resolvedDestination = $this->pathValidator->validate($destination, mustExist: false);
        }

        $this->filesystem->rename($resolvedSource, $resolvedDestination, overwrite: true);

        return \sprintf('Successfully moved "%s" to "%s".', $source, $destination);
    }

    /**
     * Delete a file or directory.
     *
     * @throws OperationNotPermittedException If delete operations are not allowed
     * @throws PathSecurityException          If the path violates security constraints
     */
    public function delete(string $path): string
    {
        $this->assertDeleteAllowed();

        if (is_dir($path) || is_dir($this->pathValidator->getBasePath().'/'.$path)) {
            $resolvedPath = $this->pathValidator->validateDirectory($path);
        } else {
            $resolvedPath = $this->pathValidator->validate($path);
        }

        $this->filesystem->remove($resolvedPath);

        return \sprintf('Successfully deleted "%s".', $path);
    }

    /**
     * Create a directory.
     *
     * @throws OperationNotPermittedException If write operations are not allowed
     * @throws PathSecurityException          If the path violates security constraints
     */
    public function mkdir(string $path): string
    {
        $this->assertWriteAllowed();

        $resolvedPath = $this->pathValidator->validateDirectory($path, mustExist: false);

        $this->filesystem->mkdir($resolvedPath);

        return \sprintf('Successfully created directory "%s".', $path);
    }

    /**
     * Check if a file or directory exists.
     *
     * @return array{exists: bool, type: string|null}
     *
     * @throws PathSecurityException If the path violates security constraints
     */
    public function exists(string $path): array
    {
        try {
            $resolvedPath = $this->pathValidator->validateDirectory($path);
            $exists = true;
            $type = is_dir($resolvedPath) ? 'directory' : 'file';
        } catch (PathSecurityException $e) {
            if (str_contains($e->getMessage(), 'does not exist')) {
                $exists = false;
                $type = null;
            } else {
                throw $e;
            }
        }

        return [
            'exists' => $exists,
            'type' => $type,
        ];
    }

    /**
     * Get file metadata (size, modified time, permissions).
     *
     * @return array{
     *     path: string,
     *     type: string,
     *     size: int,
     *     modified: string,
     *     permissions: string,
     *     readable: bool,
     *     writable: bool,
     * }
     *
     * @throws PathSecurityException If the path violates security constraints
     */
    public function info(string $path): array
    {
        $resolvedPath = $this->pathValidator->validateDirectory($path);

        $isDir = is_dir($resolvedPath);

        return [
            'path' => $path,
            'type' => $isDir ? 'directory' : 'file',
            'size' => $isDir ? 0 : (int) filesize($resolvedPath),
            'modified' => date('Y-m-d H:i:s', (int) filemtime($resolvedPath)),
            'permissions' => substr(\sprintf('%o', fileperms($resolvedPath)), -4),
            'readable' => is_readable($resolvedPath),
            'writable' => is_writable($resolvedPath),
        ];
    }

    /**
     * List contents of a directory.
     *
     * @return list<array{name: string, type: string, size: int}>
     *
     * @throws PathSecurityException If the path violates security constraints
     */
    public function list(string $path, bool $recursive = false): array
    {
        $resolvedPath = $this->pathValidator->validateDirectory($path);

        if (!is_dir($resolvedPath)) {
            throw new PathSecurityException(\sprintf('Path "%s" is not a directory.', $path));
        }

        $finder = new Finder();
        $finder->in($resolvedPath)
            ->ignoreDotFiles(true)
            ->sortByName();

        if (!$recursive) {
            $finder->depth(0);
        }

        $items = [];

        foreach ($finder as $item) {
            $items[] = [
                'name' => $item->getRelativePathname(),
                'type' => $item->isDir() ? 'directory' : 'file',
                'size' => $item->isDir() ? 0 : $item->getSize(),
            ];
        }

        return $items;
    }

    private function assertWriteAllowed(): void
    {
        if (!$this->allowWrite) {
            throw new OperationNotPermittedException('Write operations are not permitted.');
        }
    }

    private function assertDeleteAllowed(): void
    {
        if (!$this->allowDelete) {
            throw new OperationNotPermittedException('Delete operations are not permitted.');
        }
    }
}
