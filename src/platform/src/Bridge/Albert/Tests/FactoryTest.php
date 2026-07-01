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
        $platform = Factory::createPlatform('test-key', 'https://albert.example.com');

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
        yield 'base url without version' => ['https://albert.example.com'];
        yield 'base url with trailing slash' => ['https://albert.example.com/'];
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

        Factory::createPlatform('', 'https://albert.example.com');
    }
}
