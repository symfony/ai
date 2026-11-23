<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Bridge\Generic\ModelCatalog;
use Symfony\AI\Platform\Bridge\Generic\TextToSpeechModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FactoryTest extends TestCase
{
    public function testTextToSpeechThroughThePlatform()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://127.0.0.1:3900/v1/audio/speech', $url);
            self::assertSame('{"voice":"narrator","response_format":"wav","model":"kokoro","input":"Hello world"}', $options['body']);

            return new MockResponse('RIFF-audio', ['response_headers' => ['content-type' => 'audio/wav']]);
        });

        $platform = Factory::createPlatform(
            'http://127.0.0.1:3900',
            httpClient: $httpClient,
            modelCatalog: new ModelCatalog([
                'kokoro' => [
                    'class' => TextToSpeechModel::class,
                    'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH],
                ],
            ]),
            supportsCompletions: false,
            supportsEmbeddings: false,
            supportsTextToSpeech: true,
        );

        $result = $platform->invoke('kokoro', new Text('Hello world'), ['voice' => 'narrator', 'response_format' => 'wav'])->asBinary();

        $this->assertSame('RIFF-audio', $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testTextToSpeechIsDisabledByDefault()
    {
        $platform = Factory::createPlatform(
            'http://127.0.0.1:3900',
            httpClient: new MockHttpClient(),
            modelCatalog: new ModelCatalog([
                'kokoro' => [
                    'class' => TextToSpeechModel::class,
                    'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH],
                ],
            ]),
        );

        $this->expectException(ExceptionInterface::class);

        $platform->invoke('kokoro', new Text('Hello world'))->asBinary();
    }
}
