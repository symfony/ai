<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway\Tests\Llm;

use Symfony\AI\Platform\Bridge\Scaleway\Llm\ModelClient;
use Symfony\AI\Platform\Bridge\Scaleway\Scaleway;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientOptionsTest extends ModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::MAX_OUTPUT_TOKENS => null,
            PlatformOptions::TEMPERATURE => 'temperature',
        ];
    }

    protected function sendRequest(array $options): array
    {
        $body = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$body): JsonMockResponse {
            $body = json_decode($httpOptions['body'], true);

            return new JsonMockResponse([]);
        });

        (new ModelClient($httpClient, 'scaleway-api-key'))->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['model' => 'deepseek-r1-distill-llama-70b', 'messages' => []], $options);

        return $body;
    }
}
