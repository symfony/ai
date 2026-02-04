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

#[AsTool('read_file', 'Reads content from a file')]
final class ReadFileTool
{
    public function __invoke(string $path): string
    {
        return 'Content of '.$path;
    }
}
