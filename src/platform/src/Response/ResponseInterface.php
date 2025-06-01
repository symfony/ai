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
use Symfony\AI\Platform\Response\Metadata\Metadata;
use Symfony\Contracts\HttpClient\ResponseInterface as SymfonyHttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface ResponseInterface
{
    /**
     * @return string|iterable<mixed>|object|null
     */
    public function getContent(): string|iterable|object|null;

    public function getMetadata(): Metadata;

    public function getRawResponse(): ?SymfonyHttpResponse;

    /**
     * @throws RawResponseAlreadySetException if the response is tried to be set more than once
     */
    public function setRawResponse(SymfonyHttpResponse $rawResponse): void;
}
