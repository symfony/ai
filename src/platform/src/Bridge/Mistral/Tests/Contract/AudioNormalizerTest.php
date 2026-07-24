<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;

final class AudioNormalizerTest extends TestCase
{
    public function testSupportsNormalizationForMistralModel()
    {
        $normalizer = new AudioNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Audio('audio-data', 'audio/mpeg'), context: [
            Contract::CONTEXT_MODEL => new Mistral('voxtral-small-latest'),
        ]));
    }

    public function testDoesNotSupportNormalizationWithoutModel()
    {
        $normalizer = new AudioNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(new Audio('audio-data', 'audio/mpeg')));
    }

    public function testDoesNotSupportNormalizationForOtherContent()
    {
        $normalizer = new AudioNormalizer();

        $this->assertFalse($normalizer->supportsNormalization(new Text('not audio'), context: [
            Contract::CONTEXT_MODEL => new Mistral('voxtral-small-latest'),
        ]));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new AudioNormalizer();

        $this->assertSame([Audio::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $normalizer = new AudioNormalizer();

        $normalized = $normalizer->normalize(new Audio('audio-data', 'audio/mpeg'));

        $this->assertSame([
            'type' => 'input_audio',
            'input_audio' => base64_encode('audio-data'),
        ], $normalized);
    }
}
