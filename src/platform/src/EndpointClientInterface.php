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

/**
 * Self-contained client for a single endpoint contract — owns the request
 * shape, the HTTP/transport call, and the response shape for one
 * {@see Endpoint}.
 *
 * Catalogs declare which endpoint(s) a {@see Model} speaks via
 * {@see Model::getEndpoints()}. {@see Provider} matches an incoming
 * invocation against the registered clients by endpoint identifier — either
 * the model's default endpoint, or the one explicitly requested via
 * `$options['endpoint']`.
 *
 * Extends the legacy {@see ModelClientInterface} and
 * {@see ResultConverterInterface} so a single client instance plugs into
 * the existing {@see Provider} dispatch loops on both sides.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface EndpointClientInterface extends ModelClientInterface, ResultConverterInterface
{
    /**
     * Identifier compared against {@see Endpoint::getContract()} during
     * dispatch. Convention: "{vendor}.{contract}", e.g. "voyage.embeddings".
     *
     * @return non-empty-string
     */
    public function endpoint(): string;
}
