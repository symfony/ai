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
use Symfony\AI\Agent\Context\AgentResult;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class AgentResultTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Test content');
        $options = ['temperature' => 0.5];

        $output = new AgentResult('gpt-4', $result, $messageBag, $options, new Context());

        $this->assertSame('gpt-4', $output->getModel());
        $this->assertSame($result, $output->getResult());
        $this->assertSame($messageBag, $output->getMessageBag());
        $this->assertSame($options, $output->getOptions());
    }

    public function testConstructorWithDefaultOptions()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');

        $output = new AgentResult('claude-3', $result, $messageBag, [], new Context());

        $this->assertSame('claude-3', $output->getModel());
        $this->assertSame($result, $output->getResult());
        $this->assertSame($messageBag, $output->getMessageBag());
        $this->assertSame([], $output->getOptions());
    }

    public function testGetModel()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Test');

        $output = new AgentResult('test-model', $result, $messageBag, [], new Context());

        $this->assertSame('test-model', $output->getModel());
    }

    public function testGetResult()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Expected content');

        $output = new AgentResult('model', $result, $messageBag, [], new Context());

        $retrievedResult = $output->getResult();

        $this->assertSame($result, $retrievedResult);
        $this->assertSame('Expected content', $retrievedResult->getContent());
    }

    public function testSetResult()
    {
        $messageBag = new MessageBag();
        $originalResult = new TextResult('Original');
        $output = new AgentResult('model', $originalResult, $messageBag, [], new Context());

        $newResult = new TextResult('New content');
        $output->setResult($newResult);

        $retrievedResult = $output->getResult();
        $this->assertSame($newResult, $retrievedResult);
        $this->assertSame('New content', $retrievedResult->getContent());
    }

    public function testGetMessageBag()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('User message'));
        $messageBag->add(Message::ofAssistant('Assistant reply'));

        $result = new TextResult('Content');
        $output = new AgentResult('model', $result, $messageBag, [], new Context());

        $retrievedMessageBag = $output->getMessageBag();

        $this->assertSame($messageBag, $retrievedMessageBag);
        $this->assertCount(2, $retrievedMessageBag);
    }

    public function testGetOptions()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');
        $options = ['max_tokens' => 500, 'temperature' => 0.8];

        $output = new AgentResult('model', $result, $messageBag, $options, new Context());

        $this->assertSame($options, $output->getOptions());
        $this->assertSame(500, $output->getOptions()['max_tokens']);
        $this->assertSame(0.8, $output->getOptions()['temperature']);
    }

    public function testModelIsReadOnly()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');

        $output = new AgentResult('original-model', $result, $messageBag, [], new Context());

        $this->assertSame('original-model', $output->getModel());
    }

    public function testMessageBagIsReadOnly()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Test'));

        $result = new TextResult('Content');
        $output = new AgentResult('model', $result, $messageBag, [], new Context());

        $retrievedBag = $output->getMessageBag();
        $this->assertSame($messageBag, $retrievedBag);
    }

    public function testOptionsIsReadOnly()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');
        $options = ['key' => 'value'];

        $output = new AgentResult('model', $result, $messageBag, $options, new Context());

        $this->assertSame($options, $output->getOptions());
    }
}
