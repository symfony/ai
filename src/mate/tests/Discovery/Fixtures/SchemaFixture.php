<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery\Fixtures;

/**
 * Method signatures used to exercise the schema generator.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SchemaFixture
{
    /**
     * @param int         $limit  Maximum number of items
     * @param string|null $filter Optional filter
     * @param bool        $deep   Whether to recurse
     */
    public function scalars(int $limit, ?string $filter = null, bool $deep = false): string
    {
        return '';
    }

    /**
     * @param string[] $tags  List of tags
     * @param float    $ratio A ratio
     */
    public function arraysAndFloats(array $tags, float $ratio = 1.0): string
    {
        return '';
    }

    public function withEnum(SampleColor $color = SampleColor::Red): string
    {
        return '';
    }

    public function noParameters(): string
    {
        return '';
    }
}
