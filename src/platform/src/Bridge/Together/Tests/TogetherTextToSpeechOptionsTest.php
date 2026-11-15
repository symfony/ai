<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together\Tests;

use Symfony\AI\Platform\Bridge\Together\Together;
use Symfony\AI\Platform\Bridge\Together\TogetherClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\SpeechModelClientOptionsTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TogetherTextToSpeechOptionsTest extends SpeechModelClientOptionsTestCase
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

            return new MockResponse();
        }, 'https://api.together.xyz');

        $options[PlatformOptions::PROVIDER_OPTIONS] = ($options[PlatformOptions::PROVIDER_OPTIONS] ?? []) + ['voice' => 'friendly'];
        (new TogetherClient($httpClient))->request(new Together('cartesia/sonic', [Capability::TEXT_TO_SPEECH, Capability::OUTPUT_AUDIO]), 'Hallo', $options);

        return $sent;
    }
}
