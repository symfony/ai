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

final class ToolNoAttribute1
{
    /**
     * @param string $name  the name of the person
     * @param int    $years the age of the person
     */
    public function __invoke(string $name, int $years): string
    {
        return \sprintf('Happy Birthday, %s! You are %d years old.', $name, $years);
    }
}
