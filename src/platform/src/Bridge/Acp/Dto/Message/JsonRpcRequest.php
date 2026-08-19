<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Dto\Message;

/**
 * JSON-RPC Request.
 */
final class JsonRpcRequest
{
    public string $jsonrpc = '2.0';

    public int|string|null $id = null;

    public string $method = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $params = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $meta = null;
}
