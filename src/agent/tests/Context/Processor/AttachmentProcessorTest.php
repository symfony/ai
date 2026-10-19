<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Context\Processor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Context\AgentContext;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\Processor\AttachmentProcessor;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

final class AttachmentProcessorTest extends TestCase
{
    public function testItAttachesTheContentToTheUserMessage()
    {
        $messages = new MessageBag(Message::ofUser('What is on this image?'));
        $image = new ImageUrl('https://example.com/cat.png');

        $this->process($messages, $image);

        $userMessage = $messages->getMessages()[0];
        $this->assertInstanceOf(UserMessage::class, $userMessage);
        $this->assertCount(2, $userMessage->getContent());
        $this->assertInstanceOf(Text::class, $userMessage->getContent()[0]);
        $this->assertSame($image, $userMessage->getContent()[1]);
    }

    public function testItAttachesTheContentToTheLatestUserMessage()
    {
        $messages = new MessageBag(
            Message::ofUser('Hi!'),
            Message::ofAssistant('Hello, how can I help?'),
            Message::ofUser('What is on this image?'),
        );
        $image = new ImageUrl('https://example.com/cat.png');

        $this->process($messages, $image);

        $this->assertCount(1, $messages->getMessages()[0]->getContent());
        $this->assertSame($image, $messages->getMessages()[2]->getContent()[1]);
    }

    public function testItAttachesSeveralContentsInOrder()
    {
        $messages = new MessageBag(Message::ofUser('Compare these images.'));
        $first = new ImageUrl('https://example.com/cat.png');
        $second = new ImageUrl('https://example.com/dog.png');

        $this->process($messages, $first, $second);

        $content = $messages->getMessages()[0]->getContent();
        $this->assertCount(3, $content);
        $this->assertSame($first, $content[1]);
        $this->assertSame($second, $content[2]);
    }

    public function testItDoesNothingWithoutAUserMessage()
    {
        $messages = new MessageBag(Message::forSystem('You are a helpful assistant.'));

        $this->process($messages, new ImageUrl('https://example.com/cat.png'));

        $this->assertCount(1, $messages);
    }

    private function process(MessageBag $messages, object ...$items): void
    {
        (new AttachmentProcessor())->process(new AgentRequest('gpt-4o', $messages, [], new Context(...$items)), new AgentContext(new MockAgent()));
    }
}
