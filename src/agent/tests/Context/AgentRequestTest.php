<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Context\AgentRequest;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class AgentRequestTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $messageBag = new MessageBag();
        $options = ['temperature' => 0.7, 'max_tokens' => 100];

        $input = new AgentRequest('gpt-4', $messageBag, $options, new Context());

        $this->assertSame('gpt-4', $input->getModel());
        $this->assertSame($messageBag, $input->getMessageBag());
        $this->assertSame($options, $input->getOptions());
    }

    public function testConstructorWithDefaultOptions()
    {
        $messageBag = new MessageBag();

        $input = new AgentRequest('claude-3', $messageBag, [], new Context());

        $this->assertSame('claude-3', $input->getModel());
        $this->assertSame($messageBag, $input->getMessageBag());
        $this->assertSame([], $input->getOptions());
    }

    public function testGetModel()
    {
        $messageBag = new MessageBag();
        $input = new AgentRequest('test-model', $messageBag, [], new Context());

        $this->assertSame('test-model', $input->getModel());
    }

    public function testSetModel()
    {
        $messageBag = new MessageBag();
        $input = new AgentRequest('original-model', $messageBag, [], new Context());

        $input->setModel('new-model');

        $this->assertSame('new-model', $input->getModel());
    }

    public function testGetMessageBag()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Hello'));

        $input = new AgentRequest('model', $messageBag, [], new Context());

        $result = $input->getMessageBag();

        $this->assertSame($messageBag, $result);
        $this->assertCount(1, $result);
    }

    public function testSetMessageBag()
    {
        $originalMessageBag = new MessageBag();
        $input = new AgentRequest('model', $originalMessageBag, [], new Context());

        $newMessageBag = new MessageBag();
        $newMessageBag->add(Message::ofUser('New message'));

        $input->setMessageBag($newMessageBag);

        $result = $input->getMessageBag();
        $this->assertSame($newMessageBag, $result);
        $this->assertCount(1, $result);
    }

    public function testGetOptions()
    {
        $messageBag = new MessageBag();
        $options = ['foo' => 'bar', 'baz' => 42];

        $input = new AgentRequest('model', $messageBag, $options, new Context());

        $this->assertSame($options, $input->getOptions());
    }

    public function testSetOptions()
    {
        $messageBag = new MessageBag();
        $input = new AgentRequest('model', $messageBag, ['old' => 'option'], new Context());

        $newOptions = ['new' => 'options', 'count' => 3];
        $input->setOptions($newOptions);

        $this->assertSame($newOptions, $input->getOptions());
    }

    public function testSetOptionsReplacesAllOptions()
    {
        $messageBag = new MessageBag();
        $input = new AgentRequest('model', $messageBag, ['a' => 1, 'b' => 2], new Context());

        $input->setOptions(['c' => 3]);

        $options = $input->getOptions();
        $this->assertArrayHasKey('c', $options);
        $this->assertArrayNotHasKey('a', $options);
        $this->assertArrayNotHasKey('b', $options);
    }
}
