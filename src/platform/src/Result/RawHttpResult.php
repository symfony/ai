<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Result\Stream\HttpStreamInterface;
use Symfony\AI\Platform\Result\Stream\SseStream;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RawHttpResult implements RawResultInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly HttpStreamInterface $httpStream = new SseStream(),
    ) {
    }

    /**
     * Replaces the wrapped response with a safe summary in dumps.
     *
     * The response is typically a Symfony HttpClient {@see \Symfony\Component\HttpClient\Response\AsyncResponse}
     * (every HTTP-based bridge streams through {@see \Symfony\Component\HttpClient\EventSourceHttpClient}), which
     * has no dedicated VarDumper caster and keeps its network stream alive until it is explicitly consumed. Letting
     * a dumper reflect into its internals can drive that stream forward outside of the client's own bookkeeping, so
     * dump() and dd() must never see its live state.
     *
     * The array keys reuse PHP's own mangled-property-name format ("\0Class\0property") on purpose: it is the only
     * way for __debugInfo() to *replace* a specific property's value for both var_dump() and VarDumper's dump(),
     * rather than adding a same-named entry next to the original one.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            "\0".self::class."\0response" => \sprintf('%s (hidden from dumps to avoid consuming the underlying HTTP stream)', $this->response::class),
            "\0".self::class."\0httpStream" => $this->httpStream,
        ];
    }

    public function getData(): array
    {
        return $this->response->toArray(false);
    }

    public function getDataStream(): iterable
    {
        return $this->httpStream->stream($this->response);
    }

    public function getObject(): ResponseInterface
    {
        return $this->response;
    }
}
