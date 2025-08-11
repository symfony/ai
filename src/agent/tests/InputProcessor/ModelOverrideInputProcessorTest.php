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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\ModelOverrideInputProcessor;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Message\MessageBag;

#[CoversClass(ModelOverrideInputProcessor::class)]
#[UsesClass(Gpt::class)]
#[UsesClass(Claude::class)]
#[UsesClass(Input::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Embeddings::class)]
#[Small]
final class ModelOverrideInputProcessorTest extends TestCase
{
    public function testProcessInputWithValidModelOption()
    {
        $gpt = Gpt::create();
        $claude = Claude::create();
        $input = new Input($gpt, new MessageBag(), ['model' => $claude]);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        $this->assertSame($claude, $input->model);
    }

    public function testProcessInputWithoutModelOption()
    {
        $gpt = Gpt::create();
        $input = new Input($gpt, new MessageBag(), []);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);

        $this->assertSame($gpt, $input->model);
    }

    public function testProcessInputWithInvalidModelOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "model" must be an instance of "Symfony\AI\Platform\Model".');

        $gpt = Gpt::create();
        $model = new MessageBag();
        $input = new Input($gpt, new MessageBag(), ['model' => $model]);

        $processor = new ModelOverrideInputProcessor();
        $processor->processInput($input);
    }
}
