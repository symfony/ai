<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

final class OutputTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Test content');
        $options = ['temperature' => 0.5];

        $output = new Output('gpt-4', $result, $messageBag, $options);

        $this->assertSame('gpt-4', $output->getModel());
        $this->assertSame($result, $output->getResult());
        $this->assertSame($messageBag, $output->getMessageBag());
        $this->assertSame($options, $output->getOptions());
    }

    public function testConstructorWithDefaultOptions()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');

        $output = new Output('claude-3', $result, $messageBag);

        $this->assertSame('claude-3', $output->getModel());
        $this->assertSame($result, $output->getResult());
        $this->assertSame($messageBag, $output->getMessageBag());
        $this->assertSame([], $output->getOptions());
    }

    public function testGetModel()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Test');

        $output = new Output('test-model', $result, $messageBag);

        $this->assertSame('test-model', $output->getModel());
    }

    public function testGetResult()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Expected content');

        $output = new Output('model', $result, $messageBag);

        $retrievedResult = $output->getResult();

        $this->assertSame($result, $retrievedResult);
        $this->assertSame('Expected content', $retrievedResult->getContent());
    }

    public function testSetResult()
    {
        $messageBag = new MessageBag();
        $originalResult = new TextResult('Original');
        $output = new Output('model', $originalResult, $messageBag);

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
        $output = new Output('model', $result, $messageBag);

        $retrievedMessageBag = $output->getMessageBag();

        $this->assertSame($messageBag, $retrievedMessageBag);
        $this->assertCount(2, $retrievedMessageBag);
    }

    public function testGetOptions()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');
        $options = ['max_tokens' => 500, 'temperature' => 0.8];

        $output = new Output('model', $result, $messageBag, $options);

        $this->assertSame($options, $output->getOptions());
        $this->assertSame(500, $output->getOptions()['max_tokens']);
        $this->assertSame(0.8, $output->getOptions()['temperature']);
    }

    public function testModelIsReadOnly()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');

        $output = new Output('original-model', $result, $messageBag);

        $this->assertSame('original-model', $output->getModel());
    }

    public function testMessageBagIsReadOnly()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Test'));

        $result = new TextResult('Content');
        $output = new Output('model', $result, $messageBag);

        $retrievedBag = $output->getMessageBag();
        $this->assertSame($messageBag, $retrievedBag);
    }

    public function testOptionsIsReadOnly()
    {
        $messageBag = new MessageBag();
        $result = new TextResult('Content');
        $options = ['key' => 'value'];

        $output = new Output('model', $result, $messageBag, $options);

        $this->assertSame($options, $output->getOptions());
    }
}
