<?php

namespace Symfony\AI\Platform\Connector;

use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de
 */
final readonly class HttpResult implements ResultInterface
{
    public function __construct(
        private HttpResponseInterface $response,
    ) {
    }

    public function getRawData(): array
    {
        return $this->response->toArray(false);
    }

    public function getRawObject(): HttpResponseInterface
    {
        return $this->response;
    }
}
