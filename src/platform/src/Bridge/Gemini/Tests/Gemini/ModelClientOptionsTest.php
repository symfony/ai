<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientOptionsTest extends ModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::MAX_OUTPUT_TOKENS => 'maxOutputTokens',
            PlatformOptions::TEMPERATURE => 'temperature',
        ];
    }

    protected function sendRequest(array $options): array
    {
        $generationConfig = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$generationConfig): JsonMockResponse {
            $generationConfig = json_decode($httpOptions['body'], true)['generationConfig'] ?? [];

            return new JsonMockResponse(['candidates' => []]);
        });

        (new ModelClient($httpClient, 'test-api-key'))->request(new Gemini('gemini-2.5-flash'), ['contents' => []], $options);

        return $generationConfig;
    }
}
