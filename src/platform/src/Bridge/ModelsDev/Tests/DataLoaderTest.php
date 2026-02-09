<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ModelsDev\DataLoader;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class DataLoaderTest extends TestCase
{
    protected function tearDown(): void
    {
        DataLoader::clearCache();
    }

    public function testLoadDefaultFile()
    {
        $data = DataLoader::load();

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('openai', $data);
    }

    public function testLoadCustomFile()
    {
        $fixtureFile = $this->createFixtureFile([
            'custom-provider' => [
                'id' => 'custom-provider',
                'name' => 'Custom Provider',
                'models' => [],
            ],
        ]);

        try {
            $data = DataLoader::load($fixtureFile);

            $this->assertArrayHasKey('custom-provider', $data);
            $this->assertSame('Custom Provider', $data['custom-provider']['name']);
        } finally {
            unlink($fixtureFile);
        }
    }

    public function testCachingWorks()
    {
        $fixtureFile1 = $this->createFixtureFile(['provider1' => ['id' => 'provider1']]);
        $fixtureFile2 = $this->createFixtureFile(['provider2' => ['id' => 'provider2']]);

        try {
            // First load
            $data1 = DataLoader::load($fixtureFile1);
            $this->assertArrayHasKey('provider1', $data1);

            // Second load with same path should return cached data
            $data1Again = DataLoader::load($fixtureFile1);
            $this->assertSame($data1, $data1Again);

            // Different path should load new data
            $data2 = DataLoader::load($fixtureFile2);
            $this->assertArrayHasKey('provider2', $data2);
            $this->assertArrayNotHasKey('provider1', $data2);
        } finally {
            unlink($fixtureFile1);
            unlink($fixtureFile2);
        }
    }

    public function testClearCache()
    {
        $fixtureFile = $this->createFixtureFile(['test' => ['id' => 'test']]);

        try {
            // Load data
            $data1 = DataLoader::load($fixtureFile);
            $this->assertArrayHasKey('test', $data1);

            // Clear cache
            DataLoader::clearCache();

            // Load again should re-read from file
            $data2 = DataLoader::load($fixtureFile);
            $this->assertArrayHasKey('test', $data2);
            // While the data is the same, it's a different array instance after clearing cache
            $this->assertEquals($data1, $data2);
        } finally {
            unlink($fixtureFile);
        }
    }

    public function testNonExistentFileThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        DataLoader::load('/non/existent/path.json');
    }

    public function testInvalidJsonThrowsException()
    {
        $fixtureFile = sys_get_temp_dir().'/invalid-'.uniqid().'.json';
        file_put_contents($fixtureFile, 'invalid json{]');

        try {
            $this->expectException(\JsonException::class);
            DataLoader::load($fixtureFile);
        } finally {
            unlink($fixtureFile);
        }
    }

    public function testNonArrayDataThrowsException()
    {
        $fixtureFile = sys_get_temp_dir().'/non-array-'.uniqid().'.json';
        file_put_contents($fixtureFile, json_encode('not an array', \JSON_THROW_ON_ERROR));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Invalid models.dev API data');
            DataLoader::load($fixtureFile);
        } finally {
            unlink($fixtureFile);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createFixtureFile(array $data): string
    {
        $path = sys_get_temp_dir().'/data-loader-test-'.uniqid().'.json';
        file_put_contents($path, json_encode($data, \JSON_THROW_ON_ERROR));

        return $path;
    }
}
