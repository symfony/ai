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
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class InputTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $messageBag = new MessageBag();
        $options = ['temperature' => 0.7, 'max_tokens' => 100];

        $input = new Input('gpt-4', $messageBag, $options);

        $this->assertSame('gpt-4', $input->getModel());
        $this->assertSame($messageBag, $input->getMessageBag());
        $this->assertSame($options, $input->getOptions());
    }

    public function testConstructorWithDefaultOptions()
    {
        $messageBag = new MessageBag();

        $input = new Input('claude-3', $messageBag);

        $this->assertSame('claude-3', $input->getModel());
        $this->assertSame($messageBag, $input->getMessageBag());
        $this->assertSame([], $input->getOptions());
    }

    public function testGetModel()
    {
        $messageBag = new MessageBag();
        $input = new Input('test-model', $messageBag);

        $this->assertSame('test-model', $input->getModel());
    }

    public function testSetModel()
    {
        $messageBag = new MessageBag();
        $input = new Input('original-model', $messageBag);

        $input->setModel('new-model');

        $this->assertSame('new-model', $input->getModel());
    }

    public function testGetMessageBag()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Hello'));

        $input = new Input('model', $messageBag);

        $result = $input->getMessageBag();

        $this->assertSame($messageBag, $result);
        $this->assertCount(1, $result);
    }

    public function testSetMessageBag()
    {
        $originalMessageBag = new MessageBag();
        $input = new Input('model', $originalMessageBag);

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

        $input = new Input('model', $messageBag, $options);

        $this->assertSame($options, $input->getOptions());
    }

    public function testSetOptions()
    {
        $messageBag = new MessageBag();
        $input = new Input('model', $messageBag, ['old' => 'option']);

        $newOptions = ['new' => 'options', 'count' => 3];
        $input->setOptions($newOptions);

        $this->assertSame($newOptions, $input->getOptions());
    }

    public function testSetOptionsReplacesAllOptions()
    {
        $messageBag = new MessageBag();
        $input = new Input('model', $messageBag, ['a' => 1, 'b' => 2]);

        $input->setOptions(['c' => 3]);

        $options = $input->getOptions();
        $this->assertArrayHasKey('c', $options);
        $this->assertArrayNotHasKey('a', $options);
        $this->assertArrayNotHasKey('b', $options);
    }
}
