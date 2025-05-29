<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Server;

use PhpLlm\McpSdk\Message\Error;
use PhpLlm\McpSdk\Message\Request;
use PhpLlm\McpSdk\Message\Response;

interface RequestHandlerInterface
{
    public function supports(Request $message): bool;

    public function createResponse(Request $message): Response|Error;
}
