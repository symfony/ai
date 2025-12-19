<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\EventListener\TemplateRendererListener;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\TemplateRenderer\StringTemplateRenderer;
use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererRegistry;
use Symfony\AI\Platform\Model;

final class TemplateRendererListenerTest extends TestCase
{
    private TemplateRendererListener $listener;
    private Model $model;

    protected function setUp(): void
    {
        $registry = new TemplateRendererRegistry([
            new StringTemplateRenderer(),
        ]);

        $this->listener = new TemplateRendererListener($registry);
        $this->model = new Model('gpt-4o');
    }

    public function testRendersTemplateWhenTemplateVarsProvided()
    {
        $template = Template::string('Hello {name}!');
        $messageBag = new MessageBag(Message::forSystem($template));

        $event = new InvocationEvent($this->model, $messageBag, [
            'template_vars' => ['name' => 'World'],
        ]);

        ($this->listener)($event);

        $input = $event->getInput();
        $this->assertInstanceOf(MessageBag::class, $input);
        $messages = $input->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('Hello World!', $messages[0]->getContent());
    }

    public function testRemovesTemplateVarsFromOptions()
    {
        $template = Template::string('Hello {name}!');
        $messageBag = new MessageBag(Message::forSystem($template));

        $event = new InvocationEvent($this->model, $messageBag, [
            'template_vars' => ['name' => 'World'],
            'other_option' => 'value',
        ]);

        ($this->listener)($event);

        $options = $event->getOptions();
        $this->assertArrayNotHasKey('template_vars', $options);
        $this->assertArrayHasKey('other_option', $options);
    }

    public function testDoesNothingWhenTemplateVarsNotProvided()
    {
        $template = Template::string('Hello {name}!');
        $messageBag = new MessageBag(Message::forSystem($template));

        $event = new InvocationEvent($this->model, $messageBag, []);

        ($this->listener)($event);

        $input = $event->getInput();
        $this->assertInstanceOf(MessageBag::class, $input);
        $messages = $input->getMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Template::class, $messages[0]->getContent());
    }

    public function testThrowsExceptionWhenTemplateVarsIsNotArray()
    {
        $template = Template::string('Hello {name}!');
        $messageBag = new MessageBag(Message::forSystem($template));

        $event = new InvocationEvent($this->model, $messageBag, [
            'template_vars' => 'not an array',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "template_vars" option must be an array.');

        ($this->listener)($event);
    }

    public function testDoesNothingWhenInputIsNotMessageBag()
    {
        $event = new InvocationEvent($this->model, 'string input', [
            'template_vars' => ['name' => 'World'],
        ]);

        ($this->listener)($event);

        $this->assertSame('string input', $event->getInput());
    }

    public function testRendersMultipleMessages()
    {
        $template1 = Template::string('System: {role}');
        $template2 = Template::string('User: {query}');

        $messageBag = new MessageBag(
            Message::forSystem($template1),
            Message::forSystem($template2)
        );

        $event = new InvocationEvent($this->model, $messageBag, [
            'template_vars' => [
                'role' => 'assistant',
                'query' => 'help',
            ],
        ]);

        ($this->listener)($event);

        $input = $event->getInput();
        $this->assertInstanceOf(MessageBag::class, $input);
        $messages = $input->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('System: assistant', $messages[0]->getContent());
        $this->assertSame('User: help', $messages[1]->getContent());
    }

    public function testDoesNotRenderNonTemplateMessages()
    {
        $messageBag = new MessageBag(
            Message::forSystem('Plain string'),
            Message::forSystem(Template::string('Hello {name}!'))
        );

        $event = new InvocationEvent($this->model, $messageBag, [
            'template_vars' => ['name' => 'World'],
        ]);

        ($this->listener)($event);

        $input = $event->getInput();
        $this->assertInstanceOf(MessageBag::class, $input);
        $messages = $input->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('Plain string', $messages[0]->getContent());
        $this->assertSame('Hello World!', $messages[1]->getContent());
    }

    public function testRendersUserMessageTemplate()
    {
        $template = Template::string('Question: {query}');
        $messageBag = new MessageBag(Message::ofUser($template));

        $event = new InvocationEvent($this->model, $messageBag, [
            'template_vars' => ['query' => 'What is AI?'],
        ]);

        ($this->listener)($event);

        $input = $event->getInput();
        $this->assertInstanceOf(MessageBag::class, $input);
        $messages = $input->getMessages();
        $this->assertCount(1, $messages);

        $content = $messages[0]->getContent();
        $this->assertIsArray($content);
        $this->assertCount(1, $content);
        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertSame('Question: What is AI?', $content[0]->getText());
    }

    public function testRendersUserMessageWithMixedContent()
    {
        $messageBag = new MessageBag(
            Message::ofUser('Plain text', Template::string(' and {templated}'))
        );

        $event = new InvocationEvent($this->model, $messageBag, [
            'template_vars' => ['templated' => 'dynamic content'],
        ]);

        ($this->listener)($event);

        $input = $event->getInput();
        $this->assertInstanceOf(MessageBag::class, $input);
        $messages = $input->getMessages();
        $content = $messages[0]->getContent();

        $this->assertCount(2, $content);
        $this->assertInstanceOf(Text::class, $content[0]);
        $this->assertSame('Plain text', $content[0]->getText());
        $this->assertInstanceOf(Text::class, $content[1]);
        $this->assertSame(' and dynamic content', $content[1]->getText());
    }
}
