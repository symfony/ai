<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait VeniceErrorHandlingTrait
{
    use HttpStatusErrorHandlingTrait;

    /**
     * Overrides the shared lookup: Venice may answer a plain `error` string next to `error.message`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $error = $data['error'] ?? null;

        if (\is_array($error)) {
            $error = $error['message'] ?? null;
        }

        foreach ([$error, $data['message'] ?? null] as $message) {
            if (\is_string($message) && '' !== $message) {
                return $message;
            }
        }

        return null;
    }
}
