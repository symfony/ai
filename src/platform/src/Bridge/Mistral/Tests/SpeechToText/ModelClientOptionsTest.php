<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\SpeechToText;

use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText\ModelClient;
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
            $sent = self::readMultipartFields($httpOptions);

            return new JsonMockResponse(['text' => 'Hallo']);
        });

        (new ModelClient($httpClient, 'test-key'))->request(new SpeechToText('voxtral-mini-latest'), ['file' => 'audio-data'], $options);

        return $sent;
    }

    /**
     * @param array<string, mixed> $httpOptions
     *
     * @return array<string, string>
     */
    private static function readMultipartFields(array $httpOptions): array
    {
        $body = '';
        while ('' !== $chunk = $httpOptions['body'](8192)) {
            $body .= $chunk;
        }

        preg_match_all('/name="([^"]+)"[^\r\n]*\r\n(?:[^\r\n]+\r\n)*\r\n(.*?)\r\n--/s', $body, $matches, \PREG_SET_ORDER);

        $fields = [];
        foreach ($matches as [, $name, $value]) {
            $fields[$name] = $value;
        }

        return $fields;
    }
}
