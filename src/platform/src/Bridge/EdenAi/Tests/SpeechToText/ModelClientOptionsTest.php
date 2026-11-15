<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\SpeechToText;

use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText\ModelClient;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientOptionsTest extends SpeechModelClientOptionsTestCase
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
            $sent = json_decode($httpOptions['body'], true)['input'];

            return new JsonMockResponse(['status' => 'success', 'output' => []]);
        });

        (new ModelClient($httpClient, 'https://api.edenai.run', 'test-key'))->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3', $options);

        return $sent;
    }
}
