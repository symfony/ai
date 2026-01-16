<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class MessageBagNormalizerTest extends TestCase
{
    public function testNormalizerIsConfigured()
    {
        $normalizer = new MessageBagNormalizer(new MessageNormalizer());

        $this->assertSame([
            MessageBag::class => true,
        ], $normalizer->getSupportedTypes(''));

        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
        $this->assertTrue($normalizer->supportsNormalization(new MessageBag()));

        $this->assertFalse($normalizer->supportsDenormalization('', \stdClass::class));
        $this->assertTrue($normalizer->supportsDenormalization('', MessageBag::class));
    }

    public function testNormalizerCanNormalize()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Hello world'),
        );

        $normalizer = new MessageBagNormalizer(new MessageNormalizer());

        $data = $normalizer->normalize($messageBag);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('messages', $data);
        $this->assertCount(1, $data['messages']);
        $this->assertArrayHasKey('addedAt', $data);
    }

    public function testNormalizerCanDenormalize()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Hello world'),
        );

        $normalizer = new MessageBagNormalizer(new MessageNormalizer());

        $data = $normalizer->normalize($messageBag);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('messages', $data);
        $this->assertCount(1, $data['messages']);

        $bag = $normalizer->denormalize($data, MessageBag::class);

        $this->assertCount(1, $bag);
        $this->assertSame($data['id'], $bag->getUserMessage()->getMetadata()->get('bag'));
    }
}
