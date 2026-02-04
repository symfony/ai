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

#[AsTool('write_file', 'Writes content to a file')]
final class WriteFileTool
{
    public function __invoke(string $path, string $content): string
    {
        return 'Written to '.$path;
    }
}
