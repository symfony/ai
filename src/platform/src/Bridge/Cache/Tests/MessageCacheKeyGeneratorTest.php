<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cache\MessageCacheKeyGenerator;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;

final class MessageCacheKeyGeneratorTest extends TestCase
{
    private MessageCacheKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MessageCacheKeyGenerator();
    }

    public function testGeneratorSupportsMessageOnly()
    {
        $this->assertTrue($this->generator->supports(Message::ofUser('Hello there')));
        $this->assertTrue($this->generator->supports(Message::forSystem('You are a helpful assistant.')));
        $this->assertFalse($this->generator->supports(new MessageBag(Message::ofUser('Hello there'))));
    }

    public function testGeneratedKeyIsDerivedFromTheContentAndNotFromTheIdentifier()
    {
        $first = Message::ofUser('Hello there');
        $second = Message::ofUser('Hello there');

        $this->assertNotSame($first->getId()->toString(), $second->getId()->toString());
        $this->assertSame($this->generator->generate($first), $this->generator->generate($second));
    }

    public function testGeneratedKeyChangesWithTheContent()
    {
        $this->assertNotSame(
            $this->generator->generate(Message::ofUser('Hello there')),
            $this->generator->generate(Message::ofUser('Goodbye there')),
        );
    }

    public function testGeneratorThrowsOnUnsupportedInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" only supports "%s" inputs, "stdClass" given.', MessageCacheKeyGenerator::class, MessageInterface::class));

        $this->generator->generate(new \stdClass());
    }
}
