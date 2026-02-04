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
use Symfony\AI\Agent\Bridge\Filesystem\Exception\PathSecurityException;
use Symfony\AI\Agent\Bridge\Filesystem\PathValidator;

class PathValidatorTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__.'/Fixtures';
    }

    public function testValidateExistingFile()
    {
        $validator = new PathValidator($this->fixturesPath, [], [], []);
        $resolved = $validator->validate('sample.txt');

        $this->assertSame(realpath($this->fixturesPath.'/sample.txt'), $resolved);
    }

    public function testValidateNestedFile()
    {
        $validator = new PathValidator($this->fixturesPath, [], [], []);
        $resolved = $validator->validate('nested/file.txt');

        $this->assertSame(realpath($this->fixturesPath.'/nested/file.txt'), $resolved);
    }

    public function testValidateThrowsOnNonExistentFile()
    {
        $validator = new PathValidator($this->fixturesPath);
        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('does not exist');

        $validator->validate('nonexistent.txt');
    }

    public function testValidateThrowsOnPathTraversal()
    {
        $validator = new PathValidator($this->fixturesPath);
        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('Path traversal detected');

        $validator->validate('../sample.txt');
    }

    public function testValidateThrowsOnPathOutsideBasePath()
    {
        $validator = new PathValidator($this->fixturesPath);
        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('outside the allowed base path');

        $validator->validate('/etc/passwd');
    }

    public function testValidateThrowsOnDeniedExtension()
    {
        $validator = new PathValidator(
            $this->fixturesPath,
            deniedExtensions: ['txt'],
            deniedPatterns: [],
        );
        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('Extension "txt" is not allowed');

        $validator->validate('sample.txt');
    }

    public function testValidateThrowsWhenExtensionNotInAllowedList()
    {
        $validator = new PathValidator(
            $this->fixturesPath,
            allowedExtensions: ['md', 'json'],
            deniedExtensions: [],
            deniedPatterns: [],
        );
        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('Extension "txt" is not in the allowed list');

        $validator->validate('sample.txt');
    }

    public function testValidateThrowsOnDeniedPattern()
    {
        $validator = new PathValidator(
            $this->fixturesPath,
            deniedExtensions: [],
            deniedPatterns: ['*.txt'],
        );
        $this->expectException(PathSecurityException::class);
        $this->expectExceptionMessage('matches denied pattern');

        $validator->validate('sample.txt');
    }

    public function testValidateNonExistentFileWithMustExistFalse()
    {
        $validator = new PathValidator($this->fixturesPath, [], [], []);
        $resolved = $validator->validate('newfile.txt', mustExist: false);

        $this->assertSame(realpath($this->fixturesPath).'/newfile.txt', $resolved);
    }

    public function testValidateDirectoryExisting()
    {
        $validator = new PathValidator($this->fixturesPath, [], [], []);
        $resolved = $validator->validateDirectory('nested');

        $this->assertSame(realpath($this->fixturesPath.'/nested'), $resolved);
    }

    public function testValidateDirectoryNonExistentWithMustExistFalse()
    {
        $validator = new PathValidator($this->fixturesPath, [], [], []);
        $resolved = $validator->validateDirectory('newdir', mustExist: false);

        $this->assertSame(realpath($this->fixturesPath).'/newdir', $resolved);
    }

    public function testGetBasePath()
    {
        $validator = new PathValidator($this->fixturesPath);

        $this->assertSame($this->fixturesPath, $validator->getBasePath());
    }
}
