<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Gpt;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ModelClient;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientOptionsTest extends ModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::MAX_OUTPUT_TOKENS => 'max_output_tokens',
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

        (new ModelClient($httpClient, 'sk-api-key'))->request(new Gpt('gpt-4o'), ['input' => []], $options);

        return $body;
    }
}
