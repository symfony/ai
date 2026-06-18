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
use Symfony\AI\Platform\Bridge\Cache\MessageBagCacheKeyGenerator;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class MessageBagCacheKeyGeneratorTest extends TestCase
{
    private MessageBagCacheKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MessageBagCacheKeyGenerator();
    }

    public function testGeneratorSupportsMessageBagOnly()
    {
        $this->assertTrue($this->generator->supports(new MessageBag(Message::ofUser('Hello there'))));
        $this->assertFalse($this->generator->supports(Message::ofUser('Hello there')));
        $this->assertFalse($this->generator->supports(new DocumentUrl('https://example.com/doc.pdf')));
    }

    public function testGeneratedKeyIsDerivedFromTheContentAndNotFromTheIdentifier()
    {
        $first = new MessageBag(Message::ofUser('Hello there'));
        $second = new MessageBag(Message::ofUser('Hello there'));

        $this->assertNotSame($first->getId()->toString(), $second->getId()->toString());
        $this->assertSame($this->generator->generate($first), $this->generator->generate($second));
    }

    public function testGeneratedKeyChangesWithTheContent()
    {
        $first = new MessageBag(Message::ofUser('Hello there'));
        $second = new MessageBag(Message::ofUser('Goodbye there'));

        $this->assertNotSame($this->generator->generate($first), $this->generator->generate($second));
    }

    public function testGeneratedKeyDoesNotContainReservedCharacters()
    {
        $key = $this->generator->generate(new MessageBag(Message::ofUser('Hello there')));

        $this->assertSame(0, preg_match('#[{}()/\\\\@:]#', $key));
    }

    public function testGeneratorThrowsOnUnsupportedInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" only supports "%s" inputs, "stdClass" given.', MessageBagCacheKeyGenerator::class, MessageBag::class));

        $this->generator->generate(new \stdClass());
    }
}
