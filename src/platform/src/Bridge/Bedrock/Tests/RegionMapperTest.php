<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\RegionMapper;

final class RegionMapperTest extends TestCase
{
    #[DataProvider('provideRegions')]
    public function testMap(string $region, string $expectedPrefix)
    {
        $this->assertSame($expectedPrefix, RegionMapper::map($region));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideRegions(): iterable
    {
        yield 'asia pacific resolves to apac' => ['ap-southeast-2', 'apac'];
        yield 'asia pacific north resolves to apac' => ['ap-northeast-1', 'apac'];
        yield 'us falls through to raw prefix' => ['us-east-1', 'us'];
        yield 'eu falls through to raw prefix' => ['eu-west-1', 'eu'];
        yield 'unknown region falls through to raw prefix' => ['af-south-1', 'af'];
        yield 'empty region resolves to empty prefix' => ['', ''];
    }
}
