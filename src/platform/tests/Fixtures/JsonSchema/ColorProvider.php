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

use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;

final class ColorProvider implements SchemaProviderInterface
{
    /**
     * @param list<string> $colors
     */
    public function __construct(private readonly array $colors)
    {
    }

    public function getSchemaFragment(): array
    {
        return ['enum' => $this->colors];
    }
}
