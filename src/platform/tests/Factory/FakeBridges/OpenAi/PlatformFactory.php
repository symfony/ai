<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PlatformFactory
{
    public static array $lastArgs = [];

    public static function create(string $apiKey, HttpClientInterface $http, array $contract): object
    {
        self::$lastArgs = compact('apiKey', 'http', 'contract');

        return (object) ['bridge' => 'openai'];
    }
}
