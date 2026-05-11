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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\Venice;
use Symfony\AI\Platform\Bridge\Venice\VenicePayload;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class VenicePayloadTest extends TestCase
{
    public function testAsCompletionPayloadReturnsMessages()
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hi'],
        ];

        $payload = new VenicePayload(['messages' => $messages]);

        $this->assertSame($messages, $payload->asCompletionPayload());
    }

    public function testAsCompletionPayloadFailsWhenPayloadIsString()
    {
        $payload = new VenicePayload('plain string');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array for completion.');
        $payload->asCompletionPayload();
    }

    public function testAsCompletionPayloadFailsWhenMessagesKeyIsMissing()
    {
        $payload = new VenicePayload(['foo' => 'bar']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must contain "messages" key for completion.');
        $payload->asCompletionPayload();
    }

    public function testAsCompletionPayloadFailsWhenMessagesIsEmpty()
    {
        $payload = new VenicePayload(['messages' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Messages must be a non-empty array.');
        $payload->asCompletionPayload();
    }

    public function testAsImageGenerationAcceptsString()
    {
        $payload = new VenicePayload('A sunset over mountains');

        $this->assertSame('A sunset over mountains', $payload->asImageGeneration());
    }

    public function testAsImageGenerationAcceptsArrayWithPromptKey()
    {
        $payload = new VenicePayload(['prompt' => 'A cat on a roof']);

        $this->assertSame('A cat on a roof', $payload->asImageGeneration());
    }

    public function testAsImageGenerationFailsWhenStringIsEmpty()
    {
        $payload = new VenicePayload('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The prompt cannot be empty.');
        $payload->asImageGeneration();
    }

    public function testAsImageGenerationFailsWhenPromptKeyIsMissing()
    {
        $payload = new VenicePayload(['foo' => 'bar']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "prompt" key is missing.');
        $payload->asImageGeneration();
    }

    public function testAsImageGenerationFailsWhenPromptIsEmptyInArray()
    {
        $payload = new VenicePayload(['prompt' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "prompt" key must be a non-empty string.');
        $payload->asImageGeneration();
    }

    public function testAsTextToSpeechPayloadAcceptsString()
    {
        $payload = new VenicePayload('Hello world');

        $this->assertSame('Hello world', $payload->asTextToSpeechPayload());
    }

    public function testAsTextToSpeechPayloadAcceptsArrayWithTextKey()
    {
        $payload = new VenicePayload(['text' => 'Hello']);

        $this->assertSame('Hello', $payload->asTextToSpeechPayload());
    }

    public function testAsTextToSpeechPayloadFailsWhenStringIsEmpty()
    {
        $payload = new VenicePayload('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The text cannot be empty.');
        $payload->asTextToSpeechPayload();
    }

    public function testAsTextToSpeechPayloadFailsWhenKeyIsMissing()
    {
        $payload = new VenicePayload(['foo' => 'bar']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "text" key is missing.');
        $payload->asTextToSpeechPayload();
    }

    public function testAsSpeechToTextPayloadReturnsAudioPath()
    {
        $payload = new VenicePayload([
            'input_audio' => [
                'path' => '/tmp/sample.mp3',
                'format' => 'mp3',
            ],
        ]);

        $this->assertSame('/tmp/sample.mp3', $payload->asSpeechToTextPayload());
    }

    public function testAsSpeechToTextPayloadFailsWhenPayloadIsString()
    {
        $payload = new VenicePayload('plain text');

        $this->expectException(InvalidArgumentException::class);
        $payload->asSpeechToTextPayload();
    }

    public function testAsSpeechToTextPayloadFailsWhenInputAudioKeyMissing()
    {
        $payload = new VenicePayload(['foo' => 'bar']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must contain an "input_audio" array key for transcription.');
        $payload->asSpeechToTextPayload();
    }

    public function testAsSpeechToTextPayloadFailsWhenPathMissing()
    {
        $payload = new VenicePayload(['input_audio' => ['format' => 'mp3']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload "input_audio" must contain a "path" string key for transcription.');
        $payload->asSpeechToTextPayload();
    }

    public function testAsEmbeddingsPayloadAcceptsString()
    {
        $payload = new VenicePayload('Embed me');

        $this->assertSame('Embed me', $payload->asEmbeddingsPayload());
    }

    public function testAsEmbeddingsPayloadAcceptsArrayWithTextKey()
    {
        $payload = new VenicePayload(['text' => 'Hello']);

        $this->assertSame('Hello', $payload->asEmbeddingsPayload());
    }

    public function testAsEmbeddingsPayloadFailsWhenEmpty()
    {
        $payload = new VenicePayload('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The text cannot be empty.');
        $payload->asEmbeddingsPayload();
    }

    public function testAsVideoGenerationPayloadForTextToVideo()
    {
        $payload = new VenicePayload(['text' => 'A peaceful river']);

        $result = $payload->asVideoGenerationPayload(
            new Venice('seedance-1-5-pro-text-to-video', [Capability::TEXT_TO_VIDEO]),
            ['duration' => '5s'],
        );

        $this->assertSame([
            'duration' => '5s',
            'prompt' => 'A peaceful river',
        ], $result);
    }

    public function testAsVideoGenerationPayloadForTextToVideoUsesPromptKey()
    {
        $payload = new VenicePayload(['prompt' => 'A peaceful river']);

        $result = $payload->asVideoGenerationPayload(
            new Venice('seedance-1-5-pro-text-to-video', [Capability::TEXT_TO_VIDEO]),
            [],
        );

        $this->assertSame(['prompt' => 'A peaceful river'], $result);
    }

    public function testAsVideoGenerationPayloadForImageToVideo()
    {
        $payload = new VenicePayload([
            'prompt' => 'Camera zoom in',
            'image_url' => 'https://example.com/img.png',
        ]);

        $result = $payload->asVideoGenerationPayload(
            new Venice('seedance-1-5-pro-image-to-video', [Capability::IMAGE_TO_VIDEO]),
            [],
        );

        $this->assertSame([
            'prompt' => 'Camera zoom in',
            'image_url' => 'https://example.com/img.png',
        ], $result);
    }

    public function testAsVideoGenerationPayloadForVideoToVideo()
    {
        $payload = new VenicePayload([
            'prompt' => 'Restyle as anime',
            'video_url' => 'https://example.com/source.mp4',
        ]);

        $result = $payload->asVideoGenerationPayload(
            new Venice('runway-gen4-aleph', [Capability::VIDEO_TO_VIDEO]),
            [],
        );

        $this->assertSame([
            'prompt' => 'Restyle as anime',
            'video_url' => 'https://example.com/source.mp4',
        ], $result);
    }

    public function testAsVideoGenerationPayloadFailsWithoutPrompt()
    {
        $payload = new VenicePayload([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A valid input or a prompt is required for video generation.');
        $payload->asVideoGenerationPayload(
            new Venice('seedance-1-5-pro-text-to-video', [Capability::TEXT_TO_VIDEO]),
            [],
        );
    }

    public function testAsVideoGenerationPayloadFailsForImageToVideoWithoutImage()
    {
        $payload = new VenicePayload(['prompt' => 'A scene']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The image must be a valid URL or a data URL (ex: "data:").');
        $payload->asVideoGenerationPayload(
            new Venice('seedance-1-5-pro-image-to-video', [Capability::IMAGE_TO_VIDEO]),
            [],
        );
    }

    public function testAsVideoGenerationPayloadFailsForVideoToVideoWithoutVideo()
    {
        $payload = new VenicePayload(['prompt' => 'A scene']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The video must be a valid URL or a data URL (ex: "data:").');
        $payload->asVideoGenerationPayload(
            new Venice('runway-gen4-aleph', [Capability::VIDEO_TO_VIDEO]),
            [],
        );
    }

    public function testAsVideoGenerationPayloadFailsWhenPayloadIsString()
    {
        $payload = new VenicePayload('plain text');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array for video generation.');
        $payload->asVideoGenerationPayload(
            new Venice('seedance-1-5-pro-text-to-video', [Capability::TEXT_TO_VIDEO]),
            [],
        );
    }

    public function testAsVideoGenerationPayloadFailsForUnknownCapability()
    {
        $payload = new VenicePayload(['prompt' => 'X']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported video generation.');
        $payload->asVideoGenerationPayload(
            new Venice('foo', [Capability::INPUT_TEXT]),
            [],
        );
    }

    public function testAsImageEditPayloadReturnsImageAndPrompt()
    {
        $payload = new VenicePayload([
            'image' => 'https://example.com/in.png',
            'prompt' => 'Make it sepia',
        ]);

        $this->assertSame(
            ['image' => 'https://example.com/in.png', 'prompt' => 'Make it sepia'],
            $payload->asImageEditPayload(requirePrompt: true),
        );
    }

    public function testAsImageEditPayloadFailsWithoutImage()
    {
        $payload = new VenicePayload(['prompt' => 'X']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must contain a non-empty "image" string (base64, data URL or HTTP URL).');
        $payload->asImageEditPayload();
    }

    public function testAsImageEditPayloadFailsWithoutPromptWhenRequired()
    {
        $payload = new VenicePayload(['image' => 'data:image/png;base64,abc']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must contain a non-empty "prompt" string for image edition.');
        $payload->asImageEditPayload(requirePrompt: true);
    }

    public function testAsImageEditPayloadFailsWhenPayloadIsString()
    {
        $payload = new VenicePayload('plain');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array for image edition.');
        $payload->asImageEditPayload();
    }
}
