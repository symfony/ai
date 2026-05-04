<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SomeStructure;

#[AsTool('tool_with_nullable_class', 'Tool with nullable class parameter')]
final class ToolWithNullableClass
{
    public function __invoke(?SomeStructure $structure): ?string
    {
        return $structure?->some;
    }
}
