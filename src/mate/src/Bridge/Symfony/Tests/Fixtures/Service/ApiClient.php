<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Service;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ApiClient
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly ?object $httpClient = null,
        public readonly string $apiKey = '',
        public readonly int $timeoutSeconds = 0,
        public readonly array $scopes = [],
        public readonly bool $debug = false,
        public readonly ?string $region = null,
    ) {
    }
}
