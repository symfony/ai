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

use Symfony\AI\Platform\Exception\ResultException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class RawHttpResult implements RawResultInterface
{
    public function __construct(
        private ResponseInterface $response,
    ) {
    }

    public function getData(): array
    {
        try {
            return $this->response->toArray();
        } catch (ClientExceptionInterface $e) {
            throw new ResultException(message: \sprintf('API responded with an error: "%s"', $e->getMessage()), details: $this->response->toArray(false), previous: $e);
        } catch (ExceptionInterface $e) {
            throw new ResultException(\sprintf('Error while calling the API: "%s"', $e->getMessage()), previous: $e);
        }
    }

    public function getObject(): ResponseInterface
    {
        return $this->response;
    }
}
