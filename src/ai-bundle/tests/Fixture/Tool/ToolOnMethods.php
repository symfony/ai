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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

final class ToolOnMethods
{
    #[AsTool('tool_current', 'Current weather')]
    public function current(): string
    {
        return 'sunny';
    }

    #[AsTool('tool_forecast', 'Weather forecast')]
    public function forecast(): string
    {
        return 'rainy';
    }
}
