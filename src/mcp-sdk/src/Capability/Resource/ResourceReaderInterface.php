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

namespace Symfony\AI\McpSdk\Capability\Resource;

use Symfony\AI\McpSdk\Exception\ResourceNotFoundException;
use Symfony\AI\McpSdk\Exception\ResourceReadException;

interface ResourceReaderInterface
{
    /**
     * @throws ResourceReadException     if the resource execution fails
     * @throws ResourceNotFoundException if the resource is not found
     */
    public function read(ResourceRead $input): ResourceReadResult;
}
