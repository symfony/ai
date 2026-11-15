<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Tests;

use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ElevenLabsTextToSpeechOptionsTest extends SpeechModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::LANGUAGE => 'language_code',
        ];
    }

    protected function sendRequest(array $options): array
    {
        $sent = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$sent): MockResponse {
            $sent = json_decode($httpOptions['body'], true);

            return new MockResponse('audio');
        }, 'https://api.elevenlabs.io/v1/');

        $options[PlatformOptions::PROVIDER_OPTIONS] = ($options[PlatformOptions::PROVIDER_OPTIONS] ?? []) + ['voice' => 'Dslrhjl3ZpzrctukrQSN'];
        (new ElevenLabsClient($httpClient))->request(new ElevenLabs('eleven_multilingual_v2', [Capability::TEXT_TO_SPEECH]), 'Hallo', $options);

        return $sent;
    }
}
