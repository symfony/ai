<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Tests\Helper\UuidAssertionTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\UuidV7;

final class UserMessageTest extends TestCase
{
    use UuidAssertionTrait;

    public function testConstructionIsPossible()
    {
        $obj = new UserMessage(new Text('foo'));

        $this->assertSame(Role::User, $obj->getRole());
        $this->assertCount(1, $obj->getContent());
        $this->assertInstanceOf(Text::class, $obj->getContent()[0]);
        $this->assertSame('foo', $obj->getContent()[0]->getText());
    }

    public function testConstructionIsPossibleWithMultipleContent()
    {
        $message = new UserMessage(new Text('foo'), new ImageUrl('https://foo.com/bar.jpg'));

        $this->assertCount(2, $message->getContent());
    }

    public function testHasAudioContentWithoutAudio()
    {
        $message = new UserMessage(new Text('foo'), new Text('bar'));

        $this->assertFalse($message->hasAudioContent());
    }

    public function testHasAudioContentWithAudio()
    {
        $message = new UserMessage(new Text('foo'), Audio::fromFile(\dirname(__DIR__, 4).'/fixtures/audio.mp3'));

        $this->assertTrue($message->hasAudioContent());
    }

    public function testHasImageContentWithoutImage()
    {
        $message = new UserMessage(new Text('foo'), new Text('bar'));

        $this->assertFalse($message->hasImageContent());
    }

    public function testHasImageContentWithImage()
    {
        $message = new UserMessage(new Text('foo'), new ImageUrl('https://foo.com/bar.jpg'));

        $this->assertTrue($message->hasImageContent());
    }

    public function testMessageHasUid()
    {
        $message = new UserMessage(new Text('foo'));

        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }

    public function testDifferentMessagesHaveDifferentUids()
    {
        $message1 = new UserMessage(new Text('foo'));
        $message2 = new UserMessage(new Text('bar'));

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testSameMessagesHaveDifferentUids()
    {
        $message1 = new UserMessage(new Text('foo'));
        $message2 = new UserMessage(new Text('foo'));

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testMessageIdImplementsRequiredInterfaces()
    {
        $message = new UserMessage(new Text('test'));

        $this->assertInstanceOf(AbstractUid::class, $message->getId());
        $this->assertInstanceOf(TimeBasedUidInterface::class, $message->getId());
        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }

    public function testAsTextWithSingleTextContent()
    {
        $message = new UserMessage(new Text('Hello, world!'));

        $this->assertSame('Hello, world!', $message->asText());
    }

    public function testAsTextWithMultipleTextParts()
    {
        $message = new UserMessage(new Text('Part one'), new Text('Part two'), new Text('Part three'));

        $this->assertSame('Part one Part two Part three', $message->asText());
    }

    public function testAsTextIgnoresNonTextContent()
    {
        $message = new UserMessage(
            new Text('Text content'),
            new ImageUrl('http://example.com/image.png'),
            new Text('More text')
        );

        $this->assertSame('Text content More text', $message->asText());
    }

    public function testAsTextWithoutTextContent()
    {
        $message = new UserMessage(new ImageUrl('http://example.com/image.png'));

        $this->assertNull($message->asText());
    }

    public function testAsTextWithAudioAndImage()
    {
        $message = new UserMessage(
            Audio::fromFile(\dirname(__DIR__, 4).'/fixtures/audio.mp3'),
            new ImageUrl('http://example.com/image.png')
        );

        $this->assertNull($message->asText());
    }
}
