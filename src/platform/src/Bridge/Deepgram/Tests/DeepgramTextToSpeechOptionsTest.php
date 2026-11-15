<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\DeepgramClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeepgramTextToSpeechOptionsTest extends SpeechModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::LANGUAGE => null,
        ];
    }

    protected function sendRequest(array $options): array
    {
        $sent = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$sent): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $sent);

            return new MockResponse('audio', ['response_headers' => ['content-type' => 'audio/mpeg']]);
        }, 'https://api.deepgram.com/v1/');

        (new DeepgramClient($httpClient))->request(new Deepgram('aura-2-thalia-en', [Capability::TEXT_TO_SPEECH]), ['text' => 'Hallo'], $options);

        return $sent;
    }
}
