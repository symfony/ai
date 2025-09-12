<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('read_file', 'Read contents of a file from disk')]
#[AsTool('write_file', 'Write content to a file on disk', method: 'writeFile')]
#[AsTool('copy_file', 'Copy a file from one location to another', method: 'copyFile')]
#[AsTool('move_file', 'Move or rename a file from one location to another', method: 'moveFile')]
#[AsTool('delete_file', 'Delete a file from disk', method: 'deleteFile')]
#[AsTool('list_directory', 'List files and directories in a specified folder', method: 'listDirectory')]
final readonly class FileManagement
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
        private string $basePath = '',
    ) {
    }

    /**
     * @param string $filePath The path of the file to read
     */
    public function __invoke(string $filePath): string
    {
        try {
            $fullPath = $this->getFullPath($filePath);

            if (!file_exists($fullPath)) {
                return "Error: no such file or directory: {$filePath}";
            }

            if (!is_readable($fullPath)) {
                return "Error: file is not readable: {$filePath}";
            }

            $content = file_get_contents($fullPath);

            if (false === $content) {
                return "Error: unable to read file: {$filePath}";
            }

            return $content;
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * @param string $filePath The path of the file to write
     * @param string $content  The content to write to the file
     * @param bool   $append   Whether to append to an existing file
     */
    public function writeFile(string $filePath, string $content, bool $append = false): string
    {
        try {
            $fullPath = $this->getFullPath($filePath);

            // Create directory if it doesn't exist
            $directory = \dirname($fullPath);
            if (!is_dir($directory)) {
                $this->filesystem->mkdir($directory);
            }

            if ($append) {
                file_put_contents($fullPath, $content, \FILE_APPEND | \LOCK_EX);
            } else {
                file_put_contents($fullPath, $content, \LOCK_EX);
            }

            return "File written successfully to {$filePath}.";
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * @param string $sourcePath      The path of the file to copy
     * @param string $destinationPath The path to save the copied file
     */
    public function copyFile(string $sourcePath, string $destinationPath): string
    {
        try {
            $fullSourcePath = $this->getFullPath($sourcePath);
            $fullDestinationPath = $this->getFullPath($destinationPath);

            if (!file_exists($fullSourcePath)) {
                return "Error: no such file or directory: {$sourcePath}";
            }

            // Create destination directory if it doesn't exist
            $destinationDirectory = \dirname($fullDestinationPath);
            if (!is_dir($destinationDirectory)) {
                $this->filesystem->mkdir($destinationDirectory);
            }

            $this->filesystem->copy($fullSourcePath, $fullDestinationPath);

            return "File copied successfully from {$sourcePath} to {$destinationPath}.";
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * @param string $sourcePath      The path of the file to move
     * @param string $destinationPath The new path for the moved file
     */
    public function moveFile(string $sourcePath, string $destinationPath): string
    {
        try {
            $fullSourcePath = $this->getFullPath($sourcePath);
            $fullDestinationPath = $this->getFullPath($destinationPath);

            if (!file_exists($fullSourcePath)) {
                return "Error: no such file or directory: {$sourcePath}";
            }

            // Create destination directory if it doesn't exist
            $destinationDirectory = \dirname($fullDestinationPath);
            if (!is_dir($destinationDirectory)) {
                $this->filesystem->mkdir($destinationDirectory);
            }

            $this->filesystem->rename($fullSourcePath, $fullDestinationPath);

            return "File moved successfully from {$sourcePath} to {$destinationPath}.";
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * @param string $filePath The path of the file to delete
     */
    public function deleteFile(string $filePath): string
    {
        try {
            $fullPath = $this->getFullPath($filePath);

            if (!file_exists($fullPath)) {
                return "Error: no such file or directory: {$filePath}";
            }

            $this->filesystem->remove($fullPath);

            return "File deleted successfully: {$filePath}.";
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * @param string $dirPath The directory path to list (defaults to current directory)
     */
    public function listDirectory(string $dirPath = '.'): string
    {
        try {
            $fullPath = $this->getFullPath($dirPath);

            if (!is_dir($fullPath)) {
                return "Error: no such directory: {$dirPath}";
            }

            $entries = scandir($fullPath);

            if (false === $entries) {
                return "Error: unable to read directory: {$dirPath}";
            }

            // Filter out . and .. entries
            $entries = array_filter($entries, fn (string $entry) => '.' !== $entry && '..' !== $entry);

            if (empty($entries)) {
                return "No files found in directory {$dirPath}";
            }

            return implode("\n", $entries);
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    private function getFullPath(string $path): string
    {
        if (empty($this->basePath)) {
            return Path::canonicalize($path);
        }

        return Path::canonicalize($this->basePath.'/'.$path);
    }
}
