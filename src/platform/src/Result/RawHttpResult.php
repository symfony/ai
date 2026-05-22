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
final class RawHttpResult implements RawResultInterface, CancellableInterface
{
    private bool $cancelled = false;

    public function __construct(
        private readonly ResponseInterface $response,
        private readonly HttpStreamInterface $httpStream = new SseStream(),
    ) {
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

    public function cancel(): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;
        $this->response->cancel();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
