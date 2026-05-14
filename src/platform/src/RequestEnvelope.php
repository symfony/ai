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
 * The protocol-agnostic shape of one outbound request, produced by a
 * {@see EndpointClientInterface} and consumed by a {@see TransportInterface}.
 *
 * The envelope only declares what a contract requires — body, vendor headers,
 * and (optionally) the relative path the contract speaks. Transports decide
 * how to deliver it: HTTP transports use $path/$method; SDK-based transports
 * (e.g. Bedrock) ignore them and use $payload + $headers' content type.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RequestEnvelope
{
    /**
     * @param array<string, mixed>               $payload The request body, before serialization
     * @param array<string, string|list<string>> $headers Vendor-specific headers (e.g. anthropic-version)
     * @param non-empty-string                   $path    Relative path the contract speaks (e.g. "/v1/messages")
     * @param non-empty-string                   $method  HTTP method
     */
    public function __construct(
        private readonly array $payload,
        private readonly array $headers = [],
        private readonly string $path = '/',
        private readonly string $method = 'POST',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return non-empty-string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return non-empty-string
     */
    public function getMethod(): string
    {
        return $this->method;
    }
}
