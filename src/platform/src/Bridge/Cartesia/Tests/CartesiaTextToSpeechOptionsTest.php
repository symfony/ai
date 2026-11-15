<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia\Tests;

use Symfony\AI\Platform\Bridge\Cartesia\Cartesia;
use Symfony\AI\Platform\Bridge\Cartesia\CartesiaClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CartesiaTextToSpeechOptionsTest extends SpeechModelClientOptionsTestCase
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
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$sent): MockResponse {
            $sent = json_decode($httpOptions['body'], true);

            return new MockResponse('audio');
        });

        $options[PlatformOptions::PROVIDER_OPTIONS] = ($options[PlatformOptions::PROVIDER_OPTIONS] ?? []) + ['voice' => 'voice-id', 'output_format' => ['container' => 'mp3']];
        (new CartesiaClient($httpClient, 'my-api-key', 'foo'))->request(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH]), 'Hallo', $options);

        return $sent;
    }
}
