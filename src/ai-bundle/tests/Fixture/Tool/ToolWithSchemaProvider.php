<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Fixture\Tool;

use Symfony\AI\AiBundle\Tests\Fixture\JsonSchema\CategoryProvider;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

final class ToolWithSchemaProvider
{
    public function __invoke(
        #[Schema(provider: CategoryProvider::class)]
        string $category,
        #[Schema(provider: 'app.provider.tag')]
        string $tag,
    ): string {
        return $category.'/'.$tag;
    }
}
