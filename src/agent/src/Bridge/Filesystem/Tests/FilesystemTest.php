<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Filesystem\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Filesystem\Exception\OperationNotPermittedException;
use Symfony\AI\Agent\Bridge\Filesystem\Exception\PathSecurityException;
use Symfony\AI\Agent\Bridge\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class FilesystemTest extends TestCase
{
    private string $fixturesPath;
    private string $tempPath;
    private SymfonyFilesystem $symfonyFilesystem;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__.'/Fixtures';
        $this->tempPath = sys_get_temp_dir().'/filesystem_test_'.uniqid();
        $this->symfonyFilesystem = new SymfonyFilesystem();

        $this->symfonyFilesystem->mkdir($this->tempPath);
        $this->symfonyFilesystem->mirror($this->fixturesPath, $this->tempPath);
    }

    protected function tearDown(): void
    {
        $this->symfonyFilesystem->remove($this->tempPath);
    }

    public function testRead()
    {
        $filesystem = $this->createFilesystem();
        $content = $filesystem->read('sample.txt');

        $this->assertStringContainsString('Hello, World!', $content);
    }

    public function testReadNestedFile()
    {
        $filesystem = $this->createFilesystem();
        $content = $filesystem->read('nested/file.txt');

        $this->assertStringContainsString('nested file', $content);
    }

    public function testReadThrowsOnDirectory()
    {
        $filesystem = $this->createFilesystem();

        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('is not a file');

        $filesystem->read('nested');
    }

    public function testReadThrowsOnFileTooLarge()
    {
        $largePath = $this->tempPath.'/large.txt';
        $this->symfonyFilesystem->dumpFile($largePath, str_repeat('x', 1024));

        $filesystem = $this->createFilesystem(maxReadSize: 512);

        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('exceeds maximum read size');

        $filesystem->read('large.txt');
    }

    public function testWrite()
    {
        $filesystem = $this->createFilesystem();
        $result = $filesystem->write('newfile.txt', 'New content');

        $this->assertStringContainsString('Successfully wrote', $result);
        $this->assertFileExists($this->tempPath.'/newfile.txt');
        $this->assertSame('New content', file_get_contents($this->tempPath.'/newfile.txt'));
    }

    public function testWriteOverwritesExisting()
    {
        $filesystem = $this->createFilesystem();
        $filesystem->write('sample.txt', 'Overwritten content');

        $this->assertSame('Overwritten content', file_get_contents($this->tempPath.'/sample.txt'));
    }

    public function testWriteThrowsWhenNotAllowed()
    {
        $filesystem = $this->createFilesystem(allowWrite: false);

        $this->expectException(OperationNotPermittedException::class);
        $this->expectExceptionMessage('Write operations are not permitted');

        $filesystem->write('newfile.txt', 'content');
    }

    public function testAppend()
    {
        $filesystem = $this->createFilesystem();
        $filesystem->append('sample.txt', ' Appended!');

        $this->assertStringContainsString('Appended!', file_get_contents($this->tempPath.'/sample.txt'));
    }

    public function testAppendCreatesNewFile()
    {
        $filesystem = $this->createFilesystem();
        $filesystem->append('appended.txt', 'New content');

        $this->assertFileExists($this->tempPath.'/appended.txt');
        $this->assertSame('New content', file_get_contents($this->tempPath.'/appended.txt'));
    }

    public function testAppendThrowsWhenNotAllowed()
    {
        $filesystem = $this->createFilesystem(allowWrite: false);

        $this->expectException(OperationNotPermittedException::class);

        $filesystem->append('sample.txt', 'content');
    }

    public function testCopy()
    {
        $filesystem = $this->createFilesystem();
        $result = $filesystem->copy('sample.txt', 'sample_copy.txt');

        $this->assertStringContainsString('Successfully copied', $result);
        $this->assertFileExists($this->tempPath.'/sample_copy.txt');
        $this->assertSame(
            file_get_contents($this->tempPath.'/sample.txt'),
            file_get_contents($this->tempPath.'/sample_copy.txt')
        );
    }

    public function testCopyThrowsWhenNotAllowed()
    {
        $filesystem = $this->createFilesystem(allowWrite: false);

        $this->expectException(OperationNotPermittedException::class);

        $filesystem->copy('sample.txt', 'sample_copy.txt');
    }

    public function testMove()
    {
        $filesystem = $this->createFilesystem();
        $originalContent = file_get_contents($this->tempPath.'/sample.txt');

        $result = $filesystem->move('sample.txt', 'moved.txt');

        $this->assertStringContainsString('Successfully moved', $result);
        $this->assertFileDoesNotExist($this->tempPath.'/sample.txt');
        $this->assertFileExists($this->tempPath.'/moved.txt');
        $this->assertSame($originalContent, file_get_contents($this->tempPath.'/moved.txt'));
    }

    public function testMoveThrowsWhenNotAllowed()
    {
        $filesystem = $this->createFilesystem(allowWrite: false);

        $this->expectException(OperationNotPermittedException::class);

        $filesystem->move('sample.txt', 'moved.txt');
    }

    public function testDelete()
    {
        $filesystem = $this->createFilesystem(allowDelete: true);
        $result = $filesystem->delete('sample.txt');

        $this->assertStringContainsString('Successfully deleted', $result);
        $this->assertFileDoesNotExist($this->tempPath.'/sample.txt');
    }

    public function testDeleteDirectory()
    {
        $filesystem = $this->createFilesystem(allowDelete: true);
        $result = $filesystem->delete('nested');

        $this->assertStringContainsString('Successfully deleted', $result);
        $this->assertDirectoryDoesNotExist($this->tempPath.'/nested');
    }

    public function testDeleteThrowsWhenNotAllowed()
    {
        $filesystem = $this->createFilesystem(allowDelete: false);

        $this->expectException(OperationNotPermittedException::class);
        $this->expectExceptionMessage('Delete operations are not permitted');

        $filesystem->delete('sample.txt');
    }

    public function testMkdir()
    {
        $filesystem = $this->createFilesystem();
        $result = $filesystem->mkdir('newdir');

        $this->assertStringContainsString('Successfully created directory', $result);
        $this->assertDirectoryExists($this->tempPath.'/newdir');
    }

    public function testMkdirThrowsWhenNotAllowed()
    {
        $filesystem = $this->createFilesystem(allowWrite: false);

        $this->expectException(OperationNotPermittedException::class);

        $filesystem->mkdir('newdir');
    }

    public function testExists()
    {
        $filesystem = $this->createFilesystem();

        $result = $filesystem->exists('sample.txt');
        $this->assertTrue($result['exists']);
        $this->assertSame('file', $result['type']);

        $result = $filesystem->exists('nested');
        $this->assertTrue($result['exists']);
        $this->assertSame('directory', $result['type']);

        $result = $filesystem->exists('nonexistent.txt');
        $this->assertFalse($result['exists']);
        $this->assertNull($result['type']);
    }

    public function testInfo()
    {
        $filesystem = $this->createFilesystem();
        $info = $filesystem->info('sample.txt');

        $this->assertSame('sample.txt', $info['path']);
        $this->assertSame('file', $info['type']);
        $this->assertGreaterThan(0, $info['size']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $info['modified']);
        $this->assertMatchesRegularExpression('/\d{4}/', $info['permissions']);
        $this->assertTrue($info['readable']);
    }

    public function testInfoDirectory()
    {
        $filesystem = $this->createFilesystem();
        $info = $filesystem->info('nested');

        $this->assertSame('nested', $info['path']);
        $this->assertSame('directory', $info['type']);
        $this->assertSame(0, $info['size']);
    }

    public function testList()
    {
        $filesystem = $this->createFilesystem();
        $items = $filesystem->list('.');

        $names = array_column($items, 'name');
        $this->assertContains('sample.txt', $names);
        $this->assertContains('nested', $names);
    }

    public function testListRecursive()
    {
        $filesystem = $this->createFilesystem();
        $items = $filesystem->list('.', recursive: true);

        $names = array_column($items, 'name');
        $this->assertContains('sample.txt', $names);
        $this->assertContains('nested', $names);
        $this->assertContains('nested/file.txt', $names);
    }

    public function testListThrowsOnFile()
    {
        $filesystem = $this->createFilesystem();

        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('is not a directory');

        $filesystem->list('sample.txt');
    }

    public function testPathTraversalPrevented()
    {
        $filesystem = $this->createFilesystem();

        $this->expectException(PathSecurityException::class);

        $filesystem->read('../../../etc/passwd');
    }

    public function testDeniedExtensionPrevented()
    {
        $this->symfonyFilesystem->dumpFile($this->tempPath.'/script.php', '<?php echo "test";');

        $filesystem = $this->createFilesystem();

        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('Extension "php" is not allowed');

        $filesystem->read('script.php');
    }

    public function testDeniedPatternPrevented()
    {
        $this->symfonyFilesystem->dumpFile($this->tempPath.'/.hidden', 'secret');

        $filesystem = $this->createFilesystem();

        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('matches denied pattern');

        $filesystem->read('.hidden');
    }

    private function createFilesystem(
        bool $allowWrite = true,
        bool $allowDelete = false,
        int $maxReadSize = 10485760,
    ): Filesystem {
        return new Filesystem(
            $this->symfonyFilesystem,
            $this->tempPath,
            $allowWrite,
            $allowDelete,
            [],
            ['php', 'phar', 'sh', 'exe', 'bat'],
            ['.*', '*.env*'],
            $maxReadSize,
        );
    }
}
