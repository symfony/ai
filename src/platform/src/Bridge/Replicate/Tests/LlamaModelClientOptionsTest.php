<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate\Tests;

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Replicate\Client;
use Symfony\AI\Platform\Bridge\Replicate\LlamaModelClient;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class LlamaModelClientOptionsTest extends ModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::MAX_OUTPUT_TOKENS => null,
            PlatformOptions::TEMPERATURE => null,
        ];
    }

    protected function sendRequest(array $options): array
    {
        $input = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$input): JsonMockResponse {
            $input = json_decode($httpOptions['body'], true)['input'];

            return new JsonMockResponse([]);
        });

        (new LlamaModelClient(new Client($httpClient, 'test-key')))->request(new Llama('llama-3.1-405b-instruct'), ['system' => '', 'prompt' => 'Hello'], $options);

        return $input;
    }
}
