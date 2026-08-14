<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

final class ModelTest extends TestCase
{
    public function testReturnsName()
    {
        $model = new Model('gpt-4');

        $this->assertSame('gpt-4', $model->getName());
    }

    public function testReturnsCapabilities()
    {
        $model = new Model('gpt-4', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $this->assertSame([Capability::INPUT_TEXT, Capability::OUTPUT_TEXT], $model->getCapabilities());
    }

    public function testChecksSupportForCapability()
    {
        $model = new Model('gpt-4', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $this->assertTrue($model->supports(Capability::INPUT_TEXT));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertFalse($model->supports(Capability::INPUT_IMAGE));
    }

    public function testReturnsEmptyCapabilitiesByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertSame([], $model->getCapabilities());
    }

    public function testReturnsOptions()
    {
        $options = [
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ];
        $model = new Model('gpt-4', [], $options);

        $this->assertSame($options, $model->getOptions());
    }

    public function testReturnsEmptyOptionsByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertSame([], $model->getOptions());
    }

    public function testReturnsEmptyEndpointsByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertSame([], $model->getEndpoints());
        $this->assertFalse($model->hasEndpoints());
        $this->assertNull($model->getDefaultEndpoint());
    }

    public function testReturnsEndpoints()
    {
        $chat = new Endpoint('openai.chat_completions');
        $responses = new Endpoint('openai.responses');
        $model = new Model('gpt-4', [], [], [$chat, $responses]);

        $this->assertSame([$chat, $responses], $model->getEndpoints());
        $this->assertTrue($model->hasEndpoints());
    }

    public function testDefaultEndpointIsTheFirstDeclared()
    {
        $chat = new Endpoint('openai.chat_completions');
        $responses = new Endpoint('openai.responses');
        $model = new Model('gpt-4', [], [], [$chat, $responses]);

        $this->assertSame($chat, $model->getDefaultEndpoint());
    }

    public function testGetEndpointReturnsMatchingContract()
    {
        $responses = new Endpoint('openai.responses');
        $model = new Model('gpt-4', [], [], [new Endpoint('openai.chat_completions'), $responses]);

        $this->assertSame($responses, $model->getEndpoint('openai.responses'));
    }

    public function testGetEndpointThrowsForUnknownContract()
    {
        $model = new Model('gpt-4', [], [], [new Endpoint('openai.chat_completions')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model "gpt-4" does not support endpoint "openai.responses". Supported: "openai.chat_completions".');

        $model->getEndpoint('openai.responses');
    }

    public function testSupportsEndpoint()
    {
        $model = new Model('gpt-4', [], [], [new Endpoint('openai.chat_completions')]);

        $this->assertTrue($model->supportsEndpoint('openai.chat_completions'));
        $this->assertFalse($model->supportsEndpoint('openai.responses'));
    }
}
