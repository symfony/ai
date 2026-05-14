<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\Contract\DeepgramContract;
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Text;

final class DeepgramContractTest extends TestCase
{
    public function testCreateReturnsContract()
    {
        $contract = DeepgramContract::create();

        $this->assertInstanceOf(Contract::class, $contract);
    }

    public function testNormalizesAudio()
    {
        $contract = DeepgramContract::create();
        $model = new Deepgram('nova-3', [Capability::INPUT_AUDIO, Capability::SPEECH_TO_TEXT]);

        $payload = $contract->createRequestPayload($model, new Audio('bytes', 'audio/mpeg', '/audio.mp3'));

        $this->assertIsArray($payload);
        $this->assertSame('input_audio', $payload['type']);
        $inputAudio = $payload['input_audio'];
        $this->assertIsArray($inputAudio);
        $this->assertSame('mp3', $inputAudio['format']);
    }

    public function testNormalizesTextViaParentNormalizer()
    {
        $contract = DeepgramContract::create();
        $model = new Deepgram('aura-2-thalia-en', [Capability::INPUT_TEXT, Capability::TEXT_TO_SPEECH]);

        $payload = $contract->createRequestPayload($model, new Text('Hello world'));

        $this->assertIsArray($payload);
        $this->assertSame(['type' => 'text', 'text' => 'Hello world'], $payload);
    }

    public function testCreateAcceptsExtraNormalizers()
    {
        $custom = new class implements \Symfony\Component\Serializer\Normalizer\NormalizerInterface {
            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                return ['custom' => true];
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return $data instanceof \stdClass;
            }

            public function getSupportedTypes(?string $format): array
            {
                return [\stdClass::class => true];
            }
        };

        $contract = DeepgramContract::create([$custom]);
        $model = new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]);

        $payload = $contract->createRequestPayload($model, new \stdClass());

        $this->assertSame(['custom' => true], $payload);
    }
}
