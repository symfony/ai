<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\PlatformBridge;

use App\PlatformBridge\PlatformBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformBridgeTest extends TestCase
{
    /**
     * @return iterable<array{int|null, string|null}>
     */
    public static function downloads(): iterable
    {
        yield [null, null];
        yield [3, '3'];
        yield [999, '999'];
        yield [1_000, '1k'];
        yield [9_828, '9.8k'];
        yield [9_995, '10k'];
        yield [734_471, '734k'];
        yield [999_950, '1M'];
        yield [1_234_567, '1.2M'];
    }

    #[DataProvider('downloads')]
    public function testDownloadsAreAbbreviated(?int $downloads, ?string $expected)
    {
        $bridge = new PlatformBridge('OpenAi', 'open-ai', 'OpenAI', '', 'symfony/ai-open-ai-platform', 'https://github.com', downloads: $downloads);

        $this->assertSame($expected, $bridge->getFormattedDownloads());
    }
}
