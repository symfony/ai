<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\OllamaClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class OllamaClientOptionsTest extends ModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::MAX_OUTPUT_TOKENS => null,
            PlatformOptions::TEMPERATURE => 'options.temperature',
        ];
    }

    protected function sendRequest(array $options): array
    {
        $body = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$body): JsonMockResponse {
            $body = json_decode($httpOptions['body'], true);

            return new JsonMockResponse([]);
        }, 'http://127.0.0.1:11434');

        (new OllamaClient($httpClient))->request(new Ollama('llama3.2', [Capability::INPUT_MESSAGES]), ['messages' => []], $options);

        return $body;
    }
}
