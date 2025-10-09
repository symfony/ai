<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Psr\Log\LoggerInterface;

/**
 * Returns the current time in UTC.
 *
 * @author Tom Hart <tom.hart.221@gmail.com>
 */
#[McpTool(name: 'current-time')]
class CurrentTimeTool
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $format The format of the time, e.g. "Y-m-d H:i:s"
     */
    public function __invoke(string $format = 'Y-m-d H:i:s'): string
    {
        $this->logger->info('CurrentTimeTool called', ['format' => $format]);

        return (new \DateTime('now', new \DateTimeZone('UTC')))->format($format);
    }
}
