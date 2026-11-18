<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class TextToSpeechOptionsTest extends SpeechModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::LANGUAGE => null,
        ];
    }

    public function testTheLanguageIsNotSentInTheGenerationConfig()
    {
        $sent = $this->sendRequest([PlatformOptions::LANGUAGE => 'de']);

        $this->assertArrayHasKey('generationConfig', $sent);
        $this->assertArrayNotHasKey('language', $sent['generationConfig']);
    }

    protected function sendRequest(array $options): array
    {
        $sent = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$sent): JsonMockResponse {
            $sent = json_decode($httpOptions['body'], true);

            return new JsonMockResponse(['candidates' => []]);
        });

        $options[PlatformOptions::PROVIDER_OPTIONS] = ($options[PlatformOptions::PROVIDER_OPTIONS] ?? []) + ['responseModalities' => ['AUDIO']];
        $model = new Gemini('gemini-2.5-flash-preview-tts', [Capability::INPUT_MESSAGES, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]);
        (new ModelClient($httpClient, 'test-api-key'))->request($model, ['contents' => []], $options);

        return $sent;
    }
}
