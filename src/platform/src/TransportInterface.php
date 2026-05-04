<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * Sends a {@see RequestEnvelope} produced by a {@see EndpointClientInterface}.
 *
 * Transports own everything that varies *per provider deployment* —
 * authentication, base URL, region, deployment-name override, model-id
 * rewriting, SDK choice. They never touch payload shape; that belongs to
 * the contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface TransportInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface;
}
