<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\TextToSpeech;

use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\ModelClient;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientOptionsTest extends SpeechModelClientOptionsTestCase
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
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$sent): MockResponse {
            $sent = json_decode($httpOptions['body'], true);

            return new MockResponse('audio');
        });

        $options[PlatformOptions::PROVIDER_OPTIONS] = ($options[PlatformOptions::PROVIDER_OPTIONS] ?? []) + ['voice' => 'alloy'];
        (new ModelClient($httpClient, 'sk-api-key'))->request(new TextToSpeech('tts-1'), 'Hallo', $options);

        return $sent;
    }
}
