<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasStringBody;

/**
 * Asks Pinecone for the statistics of an index, of which the store only uses the vector counts.
 *
 * The client library ships its own request for this endpoint, but it declares no body at all, which
 * Pinecone Local rejects with "Failed to parse the request body as JSON". An empty JSON object is
 * therefore sent explicitly - as a string, since an empty array body would be encoded as "[]".
 *
 * @author Tim Lochmüller <tim.lochmueller@hdnet.de>
 *
 * @internal
 *
 * @see https://docs.pinecone.io/reference/api/2024-10/data-plane/describeindexstats
 */
final class DescribeIndexStats extends Request implements HasBody
{
    use HasStringBody;

    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/describe_index_stats';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function defaultBody(): string
    {
        return '{}';
    }
}
