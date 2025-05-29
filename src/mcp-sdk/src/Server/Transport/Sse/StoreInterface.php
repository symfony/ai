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

namespace PhpLlm\McpSdk\Server\Transport\Sse;

use Symfony\Component\Uid\Uuid;

interface StoreInterface
{
    public function push(Uuid $id, string $message): void;

    public function pop(Uuid $id): ?string;

    public function remove(Uuid $id): void;
}
