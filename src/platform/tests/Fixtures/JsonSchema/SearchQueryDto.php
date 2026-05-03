<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\JsonSchema;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;

final class SearchQueryDto
{
    public function __construct(
        #[SchemaSource(StatusProvider::class)]
        public readonly string $status,
        #[SchemaSource(ColorProvider::class)]
        public readonly string $color,
        #[SchemaSource(ContextAwareProvider::class, ['values' => ['foo', 'bar']])]
        public readonly string $category,
        #[Schema(minLength: 3)]
        public readonly string $query,
    ) {
    }

    /**
     * Same shape as a tool method, exercises buildParameters().
     */
    public function search(
        #[SchemaSource(StatusProvider::class)]
        string $status,
        #[SchemaSource(ColorProvider::class)]
        string $color,
        #[SchemaSource(ContextAwareProvider::class, ['values' => ['foo', 'bar']])]
        string $category,
        #[Schema(minLength: 3)]
        string $query,
    ): string {
        return \sprintf('%s/%s/%s/%s', $status, $color, $category, $query);
    }

    /**
     * Exercises resolution by an arbitrary (non-FQCN) service ID.
     */
    public function searchByServiceId(
        #[SchemaSource('app.provider.tag')]
        string $tag,
    ): string {
        return $tag;
    }
}
