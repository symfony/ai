<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Interface for streaming HTTP responses.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface HttpStreamInterface
{
    /**
     * Streams and decodes the HTTP response.
     *
     * @return iterable<mixed>
     */
    public function stream(ResponseInterface $response): iterable;
}
