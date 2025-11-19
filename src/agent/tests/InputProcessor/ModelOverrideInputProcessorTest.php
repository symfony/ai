<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\InputProcessor;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\ModelOverrideInputProcessor;
use Symfony\AI\Platform\Message\MessageBag;

final class ModelOverrideInputProcessorTest extends TestCase
{
    public function testProcessInputWithValidModelOption()
    {
        $input = new Input('gpt-4o-mini', new MessageBag(), ['model' => 'gpt-4o']);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        $this->assertSame('gpt-4o', $input->getModel());
    }

    public function testProcessInputWithoutModelOption()
    {
        $input = new Input('gpt-4o-mini', new MessageBag());

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        $this->assertSame('gpt-4o-mini', $input->getModel());
    }

    public function testProcessInputWithInvalidModelOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "model" must be a string.');

        $input = new Input('gpt-4o-mini', new MessageBag(), ['model' => new MessageBag()]);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);
    }
}
