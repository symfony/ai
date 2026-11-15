<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use Symfony\AI\Platform\Bridge\Venice\Venice;
use Symfony\AI\Platform\Bridge\Venice\VeniceClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class VeniceTextToSpeechOptionsTest extends SpeechModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::LANGUAGE => 'language',
        ];
    }

    protected function sendRequest(array $options): array
    {
        $sent = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$sent): JsonMockResponse {
            $sent = json_decode($httpOptions['body'], true);

            return new JsonMockResponse([]);
        }, 'https://api.venice.ai/api/v1/');

        (new VeniceClient($httpClient))->request(new Venice('tts-kokoro', [Capability::TEXT_TO_SPEECH, Capability::INPUT_TEXT]), 'Hallo', $options);

        return $sent;
    }
}
