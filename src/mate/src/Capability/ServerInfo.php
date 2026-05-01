<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Capability;

use Mcp\Capability\Attribute\McpTool;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ServerInfo
{
    /**
     * @return array{
     *     php_version: string,
     *     operating_system: string,
     *     operating_system_family: string,
     *     extensions: list<string>,
     * }
     */
    #[McpTool('server-info', 'Get PHP runtime environment details: version, OS, OS family, and loaded extensions')]
    public function getInfo(): array
    {
        return [
            'php_version' => \PHP_VERSION,
            'operating_system' => \PHP_OS,
            'operating_system_family' => \PHP_OS_FAMILY,
            'extensions' => get_loaded_extensions(),
        ];
    }
}
