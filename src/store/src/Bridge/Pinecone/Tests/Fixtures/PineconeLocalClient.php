<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone\Tests\Fixtures;

use Probots\Pinecone\Client;
use Probots\Pinecone\Resources\ControlResource;
use Probots\Pinecone\Resources\DataResource;

/**
 * Client for Pinecone Local, which - unlike the hosted service - serves the control plane and every index
 * on a different host: the control plane listens on a fixed port, while each index gets its own port assigned
 * on creation. Since the index host is therefore unknown until the index exists, it is resolved lazily.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @see https://docs.pinecone.io/guides/operations/local-development
 */
final class PineconeLocalClient extends Client
{
    public function __construct(
        string $apiKey,
        private readonly string $controlPlaneHost,
        private readonly string $indexName,
    ) {
        parent::__construct($apiKey);
    }

    public function control(): ControlResource
    {
        // Reset the base URL, since data operations point it to the index host
        $this->baseUrl = $this->controlPlaneHost;

        return parent::control();
    }

    public function data(): DataResource
    {
        if (null === $this->indexHost) {
            $host = $this->control()->index($this->indexName)->describe()->json()['host'];

            $this->setIndexHost(str_starts_with($host, 'http') ? $host : 'http://'.$host);
        }

        return parent::data();
    }
}
