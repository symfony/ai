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

#[AsTool('tool_object_float', 'A tool with object parameter with float property')]
final class ToolObjectFloat
{
    public float $height;

    public function __invoke(self $person): string
    {
        return \sprintf('Height: %.2fm', $person->height);
    }
}
