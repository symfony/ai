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

#[AsTool('tool_date', 'A tool with date parameter')]
final class ToolDate
{
    /**
     * @param \DateTimeImmutable $date The date
     */
    public function __invoke(\DateTimeImmutable $date): string
    {
        return \sprintf('Weekday: %s', $date->format('l'));
    }
}
