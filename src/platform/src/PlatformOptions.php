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
 * Names of the options that bridges translate to their own wire format.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PlatformOptions
{
    public const MAX_OUTPUT_TOKENS = 'max_output_tokens';

    /**
     * Options of the HTTP request sent to the provider, like "timeout" or "max_duration".
     *
     * The bridge's own request options, like "headers", cannot be overridden, and the payload
     * options "body" and "json" are not supported.
     */
    public const HTTP_OPTIONS = 'http_options';

    private function __construct()
    {
    }
}
