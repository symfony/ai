<?php

namespace Symfony\AI\Platform\Bridge\Azure\OpenAi;

use Symfony\Contracts\HttpClient\HttpClientInterface;
final class PlatformFactory
{
    public static array $lastArgs = [];

    public static function create(string $apiKey, HttpClientInterface $http, array $contract): object
    {
        self::$lastArgs = compact('apiKey', 'http', 'contract');
        return (object) ['bridge' => 'azure-openai'];
    }
}
