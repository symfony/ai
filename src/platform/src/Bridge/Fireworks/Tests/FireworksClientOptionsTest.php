<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Bridge\Fireworks\FireworksClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class FireworksClientOptionsTest extends ModelClientOptionsTestCase
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

        $model = new Fireworks('accounts/fireworks/models/kimi-k2p6', [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]);
        (new FireworksClient($httpClient, 'test-api-key'))->request($model, ['messages' => []], $options);

        return $body;
    }
}
