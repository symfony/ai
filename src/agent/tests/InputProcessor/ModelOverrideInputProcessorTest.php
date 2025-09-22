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
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

final class ModelOverrideInputProcessorTest extends TestCase
{
    public function testProcessInputWithValidModelOption()
    {
        $originalModel = new Model('gpt-4o-mini', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);
        $overrideModel = new Model('gpt-4o', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $input = new Input($originalModel, new MessageBag(), ['model' => $overrideModel]);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        $this->assertSame($overrideModel, $input->model);
        $this->assertSame('gpt-4o', $input->model->getName());
    }

    public function testProcessInputWithoutModelOption()
    {
        $originalModel = new Model('gpt-4o-mini', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $input = new Input($originalModel, new MessageBag());

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        $this->assertSame($originalModel, $input->model);
        $this->assertSame('gpt-4o-mini', $input->model->getName());
    }

    public function testProcessInputWithInvalidModelOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Option "model" must be an instance of "%s".', Model::class));

        $originalModel = new Model('gpt-4o-mini', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);
        $input = new Input($originalModel, new MessageBag(), ['model' => new MessageBag()]);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);
    }
}
