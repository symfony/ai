<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Fixture\JsonSchema;

use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;

final class CategoryProvider implements SchemaProviderInterface
{
    /**
     * @param list<string> $categories
     */
    public function __construct(private readonly array $categories)
    {
    }

    public function getSchemaFragment(): array
    {
        return ['enum' => $this->categories];
    }
}
