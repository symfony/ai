<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Response;

use Symfony\AI\Platform\Response\Exception\RawResponseAlreadySetException;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
trait RawResponseAwareTrait
{
    protected ?SymfonyHttpResponse $rawResponse = null;

    public function setRawResponse(SymfonyHttpResponse $rawResponse): void
    {
        if (null !== $this->rawResponse) {
            throw new RawResponseAlreadySetException();
        }

        $this->rawResponse = $rawResponse;
    }

    public function getRawResponse(): ?SymfonyHttpResponse
    {
        return $this->rawResponse;
    }
}
