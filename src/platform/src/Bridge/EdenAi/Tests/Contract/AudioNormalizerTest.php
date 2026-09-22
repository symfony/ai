<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class AudioNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new AudioNormalizer();
        $audio = new Audio('binary-audio-content', 'audio/mpeg');

        $this->assertTrue($normalizer->supportsNormalization($audio, context: [
            Contract::CONTEXT_MODEL => new SpeechToText('audio/speech_to_text_async/openai'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization($audio, context: [
            Contract::CONTEXT_MODEL => new CompletionsModel('openai/gpt-4o'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not an audio'));
    }

    public function testNormalize()
    {
        $normalizer = new AudioNormalizer();
        $audio = new Audio('binary-audio-content', 'audio/mpeg');

        $normalized = $normalizer->normalize($audio);

        $this->assertSame(['file_data'], array_keys($normalized));
        $this->assertNull($normalized['file_data']['filename']);
        $this->assertSame('audio/mpeg', $normalized['file_data']['format']);
        $this->assertSame($audio->asBase64(), $normalized['file_data']['data']);
    }
}
