<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Albert\Factory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Platform;

final class FactoryTest extends TestCase
{
    public function testCreatesPlatformWithCorrectBaseUrl()
    {
        $platform = Factory::createPlatform('test-key', 'https://albert.example.com/v1');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[DataProvider('provideValidUrls')]
    public function testHandlesUrlsCorrectly(string $url)
    {
        $platform = Factory::createPlatform('test-key', $url);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public static function provideValidUrls(): \Iterator
    {
        yield 'with v1 path' => ['https://albert.example.com/v1'];
        yield 'with v2 path' => ['https://albert.example.com/v2'];
        yield 'with v3 path' => ['https://albert.example.com/v3'];
        yield 'with v10 path' => ['https://albert.example.com/v10'];
        yield 'with v99 path' => ['https://albert.example.com/v99'];
    }

    public function testThrowsExceptionForNonHttpsUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Albert URL must start with "https://".');

        Factory::createPlatform('test-key', 'http://albert.example.com');
    }

    public function testPlatformThrowsExceptionForEmptyApiKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        Factory::createPlatform('', 'https://albert.example.com/v2');
    }

    #[DataProvider('provideUrlsWithTrailingSlash')]
    public function testThrowsExceptionForUrlsWithTrailingSlash(string $url)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Albert URL must not end with a trailing slash.');

        Factory::createPlatform('test-key', $url);
    }

    public static function provideUrlsWithTrailingSlash(): \Iterator
    {
        yield 'with trailing slash only' => ['https://albert.example.com/'];
        yield 'with v1 and trailing slash' => ['https://albert.example.com/v1/'];
        yield 'with v2 and trailing slash' => ['https://albert.example.com/v2/'];
    }

    #[DataProvider('provideUrlsWithoutVersion')]
    public function testThrowsExceptionForUrlsWithoutVersion(string $url)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Albert URL must include an API version (e.g., /v1, /v2).');

        Factory::createPlatform('test-key', $url);
    }

    public static function provideUrlsWithoutVersion(): \Iterator
    {
        yield 'without version' => ['https://albert.example.com'];
        yield 'with vx path' => ['https://albert.example.com/vx'];
        yield 'with version path' => ['https://albert.example.com/version'];
        yield 'with api path' => ['https://albert.example.com/api'];
        yield 'with v path only' => ['https://albert.example.com/v'];
        yield 'with v- path' => ['https://albert.example.com/v-1'];
    }
}
